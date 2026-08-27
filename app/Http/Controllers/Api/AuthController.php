<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Autenticacion'],
        summary: 'Registrar un nuevo cliente',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ada Lovelace'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ada@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Secret123'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+503 7000-0000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cliente creado y autenticado', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokens')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => $request->string('password'),
            'phone' => $request->input('phone'),
            'role' => 'customer',
        ]);

        $token = Auth::login($user);

        return $this->respondWithToken($token, $user, 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Autenticacion'],
        summary: 'Autenticar un cliente y obtener un token JWT',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'cliente@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Autenticacion correcta', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokens')),
            new OA\Response(response: 401, description: 'Credenciales invalidas', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $token = Auth::attempt($request->credentials());

        if (! $token) {
            return response()->json(['message' => 'Credenciales invalidas.'], 401);
        }

        return $this->respondWithToken($token, Auth::user());
    }

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Autenticacion'],
        summary: 'Obtener el cliente autenticado',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cliente autenticado',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function me(): JsonResponse
    {
        return response()->json(['data' => new UserResource(Auth::user())]);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        tags: ['Autenticacion'],
        summary: 'Refrescar el token JWT',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Nuevo token emitido', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokens')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function refresh(): JsonResponse
    {
        $token = Auth::refresh();

        return $this->respondWithToken($token, Auth::user());
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Autenticacion'],
        summary: 'Cerrar sesion e invalidar el token actual',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesion cerrada',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Sesion finalizada.')])
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'Sesion finalizada.']);
    }

    private function respondWithToken(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => new UserResource($user),
        ], $status);
    }
}
