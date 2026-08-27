<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API de E-commerce Segura',
    description: 'API REST para un e-commerce basico construida con Laravel 12: autenticacion JWT, catalogo de productos, ordenes de compra y procesamiento de pagos con Stripe. Todas las respuestas y errores usan formato JSON.',
    contact: new OA\Contact(name: 'Equipo Backend', email: 'soporte@example.com'),
    license: new OA\License(name: 'MIT', url: 'https://opensource.org/licenses/MIT'),
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Servidor de la API')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: "Introduce el token JWT devuelto por /api/auth/login (sin el prefijo 'Bearer ')."
)]
#[OA\Tag(name: 'Autenticacion', description: 'Registro, login y gestion de sesion con JWT')]
#[OA\Tag(name: 'Productos', description: 'Catalogo de productos y CRUD protegido')]
#[OA\Tag(name: 'Ordenes', description: 'Creacion de ordenes e historial de compras')]
#[OA\Tag(name: 'Pagos', description: 'Procesamiento de pagos con Stripe')]
#[OA\Schema(
    schema: 'ValidationError',
    title: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', example: ['email' => ['El campo email ya ha sido registrado.']]),
    ]
)]
#[OA\Schema(
    schema: 'Error',
    title: 'Error',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Resource not found.'),
    ]
)]
#[OA\Schema(
    schema: 'AuthTokens',
    title: 'AuthTokens',
    properties: [
        new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ]
)]
abstract class Controller
{
    //
}
