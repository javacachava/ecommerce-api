<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ConfirmPaymentRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Stripe\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    #[OA\Post(
        path: '/api/orders/{order}/payments',
        tags: ['Pagos'],
        summary: 'Iniciar el pago de una orden con Stripe (crea un PaymentIntent)',
        description: 'Devuelve el client_secret del PaymentIntent para completar el pago desde el frontend con Stripe.js, o para confirmarlo via /api/payments/{payment}/confirm en entornos de prueba.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'PaymentIntent creado', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])),
            new OA\Response(response: 200, description: 'PaymentIntent pendiente ya existente', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'La orden pertenece a otro cliente', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Orden no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'La orden no se puede pagar (ya pagada o cancelada)', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwnership($request, $order);

        if ($order->status === 'paid') {
            return response()->json(['message' => 'Esta orden ya fue pagada.'], 409);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Esta orden fue cancelada y no se puede pagar.'], 409);
        }

        $existing = $order->payment;
        if ($existing && in_array($existing->status, ['requires_payment_method', 'requires_confirmation', 'processing'], true)) {
            return response()->json(['data' => new PaymentResource($existing)], 200);
        }

        $intent = $this->stripe->createPaymentIntent($order);

        $payment = $order->payment()->create([
            'user_id' => $order->user_id,
            'gateway' => 'stripe',
            'stripe_payment_intent_id' => $intent['id'],
            'stripe_client_secret' => $intent['client_secret'],
            'status' => $intent['status'],
            'amount' => $order->total,
            'currency' => $order->currency,
            'gateway_response' => $intent['raw'],
        ]);

        return response()->json(['data' => new PaymentResource($payment)], 201);
    }

    #[OA\Post(
        path: '/api/payments/{payment}/confirm',
        tags: ['Pagos'],
        summary: 'Confirmar un pago y finalizar la compra',
        description: "Confirma el PaymentIntent en Stripe. En modo simulacion usa 'pm_card_visa' (exito) o 'pm_card_chargeDeclined' (rechazo). Al confirmarse, la orden pasa a 'paid'; si falla, la orden permanece 'pending' y el cliente puede reintentar el pago.",
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'pm_card_visa'),
            ])
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pago confirmado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
                    new OA\Property(property: 'order', ref: '#/components/schemas/Order'),
                ])
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 402, description: 'El pago fue rechazado por la pasarela', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'El pago pertenece a otro cliente', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'El pago ya fue procesado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function confirm(ConfirmPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorizeOwnership($request, $payment);

        if ($payment->status === 'succeeded') {
            return response()->json(['message' => 'Este pago ya fue completado.'], 409);
        }

        $result = $this->stripe->confirmPaymentIntent(
            $payment->stripe_payment_intent_id,
            $request->input('payment_method', 'pm_card_visa')
        );

        $order = $payment->order;

        if ($result['status'] === 'succeeded') {
            DB::transaction(function () use ($payment, $order, $result) {
                $payment->update([
                    'status' => 'succeeded',
                    'paid_at' => now(),
                    'failure_reason' => null,
                    'gateway_response' => $result['raw'],
                ]);
                $order->update(['status' => 'paid']);
            });

            return response()->json([
                'data' => new PaymentResource($payment->fresh()),
                'order' => new OrderResource($order->fresh()->load(['items', 'payment'])),
            ]);
        }

        // The payment failed. The order stays "pending" (stock remains reserved)
        // so the client can retry the payment via POST /api/orders/{order}/payments.
        $payment->update([
            'status' => 'failed',
            'failure_reason' => $result['failure_reason'] ?? 'El pago no pudo procesarse.',
            'gateway_response' => $result['raw'],
        ]);

        return response()->json([
            'message' => $result['failure_reason'] ?? 'El pago fue rechazado por la pasarela.',
            'data' => new PaymentResource($payment->fresh()),
        ], 402);
    }

    #[OA\Get(
        path: '/api/payments',
        tags: ['Pagos'],
        summary: 'Historial de transacciones del cliente autenticado',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de pagos',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', type: 'object'),
                ])
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = $request->user()->payments()
            ->with('order')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 15), 100))
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    #[OA\Get(
        path: '/api/payments/{payment}',
        tags: ['Pagos'],
        summary: 'Ver el detalle de una transaccion propia',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del pago', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')])),
            new OA\Response(response: 403, description: 'El pago pertenece a otro cliente', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Pago no encontrado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeOwnership($request, $payment);

        return response()->json(['data' => new PaymentResource($payment->load('order'))]);
    }

    private function authorizeOwnership(Request $request, Order|Payment $model): void
    {
        abort_unless(
            $model->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
            'No tienes permiso para acceder a este recurso.'
        );
    }
}
