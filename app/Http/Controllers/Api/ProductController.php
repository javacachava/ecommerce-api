<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        tags: ['Productos'],
        summary: 'Listar el catalogo publico de productos',
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Filtra por nombre o SKU', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'only_active', in: 'query', description: 'Solo productos activos (por defecto true)', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort', in: 'query', description: 'Campo de orden: price, -price, name, -created_at, stock', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Elementos por pagina (1-100)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de productos',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', type: 'object'),
                ])
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = $request->boolean('only_active', true);

        $products = Product::query()
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->when($request->filled('sort'), function ($q) use ($request) {
                $sort = (string) $request->string('sort');
                $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
                $column = ltrim($sort, '-');
                if (in_array($column, ['price', 'name', 'created_at', 'stock'], true)) {
                    $q->orderBy($column, $direction);
                }
            }, fn ($q) => $q->latest())
            ->paginate(min((int) $request->integer('per_page', 15), 100))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    #[OA\Get(
        path: '/api/products/{product}',
        tags: ['Productos'],
        summary: 'Ver el detalle de un producto',
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle del producto',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])
            ),
            new OA\Response(response: 404, description: 'Producto no encontrado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => new ProductResource($product)]);
    }

    #[OA\Post(
        path: '/api/products',
        tags: ['Productos'],
        summary: 'Crear un producto (solo administradores)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'sku', 'price', 'stock'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Teclado mecanico RGB'),
                    new OA\Property(property: 'sku', type: 'string', example: 'KB-RGB-001'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true, example: 'teclado-mecanico-rgb'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 79.90),
                    new OA\Property(property: 'stock', type: 'integer', example: 25),
                    new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'https://picsum.photos/seed/kb/600'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Requiere rol admin', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json(['data' => new ProductResource($product->refresh())], 201);
    }

    #[OA\Put(
        path: '/api/products/{product}',
        tags: ['Productos'],
        summary: 'Actualizar un producto (solo administradores)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Product')),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Requiere rol admin', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Producto no encontrado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    #[OA\Patch(
        path: '/api/products/{product}',
        tags: ['Productos'],
        summary: 'Actualizar parcialmente un producto (solo administradores)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Product')),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])),
            new OA\Response(response: 403, description: 'Requiere rol admin', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json(['data' => new ProductResource($product->fresh())]);
    }

    #[OA\Delete(
        path: '/api/products/{product}',
        tags: ['Productos'],
        summary: 'Eliminar un producto (solo administradores)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Producto eliminado'),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Requiere rol admin', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Producto no encontrado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'El producto tiene ordenes asociadas', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function destroy(Product $product): JsonResponse
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un producto con ordenes asociadas. Desactivalo en su lugar.',
            ], 409);
        }

        $product->delete();

        return response()->json(null, 204);
    }
}
