<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    #[OA\Get(
        path: '/api/orders',
        tags: ['Ordenes'],
        summary: 'Historial de compras del cliente autenticado',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtra por estado: pending, paid, failed, cancelled', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de ordenes con su detalle y pago',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['items', 'payment'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 100))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    #[OA\Post(
        path: '/api/orders',
        tags: ['Ordenes'],
        summary: 'Crear una orden de compra a partir de productos del catalogo',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            required: ['product_id', 'quantity'],
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Orden creada en estado pending', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos o stock insuficiente', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $lines = collect($request->validated('items'))->keyBy('product_id');

        $order = DB::transaction(function () use ($lines, $request) {
            /** @var \Illuminate\Support\Collection<int,Product> $products */
            $products = Product::whereIn('id', $lines->keys())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $missing = $lines->keys()->diff($products->keys());
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Algunos productos no existen o no estan disponibles: '.$missing->implode(', '),
                ]);
            }

            $order = $request->user()->orders()->create([
                'status' => 'pending',
                'currency' => config('services.stripe.currency', 'usd'),
            ]);

            foreach ($lines as $productId => $line) {
                /** @var Product $product */
                $product = $products[$productId];
                $quantity = (int) $line['quantity'];

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para '{$product->name}' (disponible: {$product->stock}).",
                    ]);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                    'line_total' => round($product->price * $quantity, 2),
                ]);

                // Reserve stock as soon as the order is placed. It is released
                // again only if the order is paid (permanent) — a failed payment
                // keeps the order "pending" so the client can retry.
                $product->decrementStock($quantity);
            }

            $order->load('items');
            $order->recalculateTotals();
            $order->save();

            return $order;
        });

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payment'])),
        ], 201);
    }

    #[OA\Get(
        path: '/api/orders/{order}',
        tags: ['Ordenes'],
        summary: 'Ver el detalle de una orden propia',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de la orden', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'La orden pertenece a otro cliente', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Orden no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwnership($request, $order);

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payment'])),
        ]);
    }

    private function authorizeOwnership(Request $request, Order $order): void
    {
        abort_unless(
            $order->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
            'No tienes permiso para acceder a esta orden.'
        );
    }
}
