# API de E-commerce Segura con Swagger Completo

API REST para un e-commerce básico construida con **Laravel 12** y **PHP 8.2+**.
Incluye autenticación con **JWT**, catálogo de productos, órdenes de compra,
procesamiento de pagos con **Stripe** y documentación **OpenAPI / Swagger UI**.

---

## Contenido

- [Stack y paquetes](#stack-y-paquetes)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración del entorno (`.env`)](#configuración-del-entorno-env)
- [Base de datos: migraciones y seeders](#base-de-datos-migraciones-y-seeders)
- [Ejecutar el proyecto](#ejecutar-el-proyecto)
- [Documentación Swagger](#documentación-swagger)
- [Autenticación JWT](#autenticación-jwt)
- [Endpoints](#endpoints)
- [Flujo de compra de ejemplo (cURL)](#flujo-de-compra-de-ejemplo-curl)
- [Integración con Stripe](#integración-con-stripe)
- [Manejo de errores](#manejo-de-errores)
- [Pruebas](#pruebas)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Publicar en GitHub](#publicar-en-github)

---

## Stack y paquetes

| Componente        | Versión / Paquete                       |
|-------------------|-----------------------------------------|
| Framework         | `laravel/framework` ^12.0               |
| PHP               | >= 8.2                                  |
| Base de datos     | MySQL 8.x                              |
| Autenticación     | `tymon/jwt-auth` ^2.0 (guard `api`)     |
| Documentación     | `darkaonline/l5-swagger` ^11 (OpenAPI 3) |
| Pasarela de pago  | `stripe/stripe-php` ^21                 |
| Validación        | Form Requests de Laravel               |
| Serialización     | API Resources de Laravel               |

---

## Requisitos

- PHP >= 8.2 con extensiones habituales de Laravel (`pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `ctype`, `json`, `curl`).
- Composer 2.x
- MySQL 8.x (o MariaDB 10.6+)
- Node no es necesario (la API no tiene frontend).

---

## Instalación

```bash
git clone <URL-DE-TU-REPO> ecommerce-api
cd ecommerce-api

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

---

## Configuración del entorno (`.env`)

Edita el archivo `.env` (ver `.env.example` con todas las variables documentadas).

### Base de datos (MySQL)

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_api
DB_USERNAME=root
DB_PASSWORD=tu_password
```

Crea la base de datos antes de migrar:

```bash
mysql -u root -p -e "CREATE DATABASE ecommerce_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### JWT

```dotenv
AUTH_GUARD=api
JWT_SECRET=<generado por: php artisan jwt:secret>
JWT_TTL=60            # minutos de vida del access token
JWT_REFRESH_TTL=20160 # minutos para poder refrescar
```

### Stripe

```dotenv
STRIPE_KEY=pk_test_tu_clave_publicable_aqui
STRIPE_SECRET=sk_test_tu_clave_secreta_aqui
STRIPE_WEBHOOK_SECRET=whsec_tu_signing_secret_aqui
STRIPE_CURRENCY=usd
```

> Si `STRIPE_SECRET` queda vacío o con un valor de marcador (el de `.env.example`,
> o cualquier valor que no tenga el formato real `sk_test_...` / `sk_live_...`),
> la API funciona en **modo simulación**: crea y confirma
> `PaymentIntents` localmente sin llamar a Stripe, para poder probar todo el flujo
> sin una cuenta. Ver [Integración con Stripe](#integración-con-stripe).

### Swagger

```dotenv
L5_SWAGGER_CONST_HOST="${APP_URL}"
L5_SWAGGER_GENERATE_ALWAYS=true   # regenera el JSON en cada carga (útil en desarrollo)
```

---

## Base de datos: migraciones y seeders

```bash
php artisan migrate --seed
```

Tablas creadas:

| Tabla         | Descripción                                        |
|---------------|----------------------------------------------------|
| `users`       | Clientes del sistema (`role`: `customer` / `admin`) |
| `products`    | Catálogo de productos                              |
| `orders`      | Órdenes de compra                                 |
| `order_items` | Detalle de productos por orden                    |
| `payments`    | Transacciones de Stripe                           |

### Datos de ejemplo (seeders)

- **`UserSeeder`** crea dos cuentas fijas más 5 clientes aleatorios:

  | Rol      | Email                  | Password      |
  |----------|------------------------|---------------|
  | admin    | `admin@example.com`    | `Password123` |
  | customer | `cliente@example.com`  | `Password123` |

- **`ProductSeeder`** carga ~20 productos (13 fijos con datos realistas + 7 aleatorios).

---

## Ejecutar el proyecto

```bash
php artisan serve
```

La API queda en `http://localhost:8000`.
Comprobación rápida: `GET http://localhost:8000/api/` devuelve `{"status":"ok", ...}`.

---

## Documentación Swagger

- **Swagger UI:** `http://localhost:8000/api/documentation`
- **OpenAPI JSON:** `http://localhost:8000/docs`

Regenerar la documentación manualmente:

```bash
php artisan l5-swagger:generate
```

Las anotaciones OpenAPI están escritas como **atributos PHP 8** (`#[OA\...]`) en
los controladores (`app/Http/Controllers/Api`) y los modelos (`app/Models`).

En Swagger UI: pulsa **Authorize**, pega el `access_token` (sin `Bearer `) y
podrás probar los endpoints protegidos.

---

## Autenticación JWT

1. `POST /api/auth/register` o `POST /api/auth/login` devuelven:

   ```json
   {
     "access_token": "eyJ0eXAiOiJKV1Qi...",
     "token_type": "bearer",
     "expires_in": 3600,
     "user": { "id": 1, "name": "...", "email": "...", "role": "customer" }
   }
   ```

2. Enviar el token en cada petición protegida:

   ```
   Authorization: Bearer <access_token>
   ```

3. `POST /api/auth/refresh` renueva el token; `POST /api/auth/logout` lo invalida.

Los endpoints de escritura de productos exigen además `role = admin`
(middleware `admin`).

---

## Endpoints

Prefijo global: `/api`

### Autenticación

| Método | Ruta               | Auth | Descripción                          |
|--------|--------------------|------|--------------------------------------|
| POST   | `/auth/register`   | —    | Registrar cliente y obtener token    |
| POST   | `/auth/login`      | —    | Iniciar sesión y obtener token       |
| GET    | `/auth/me`         | JWT  | Datos del cliente autenticado        |
| POST   | `/auth/refresh`    | JWT  | Refrescar el token                   |
| POST   | `/auth/logout`     | JWT  | Cerrar sesión / invalidar el token   |

### Productos

| Método     | Ruta               | Auth        | Descripción                       |
|------------|--------------------|-------------|-----------------------------------|
| GET        | `/products`        | —           | Listado público (paginado, filtros `search`, `sort`, `only_active`, `per_page`) |
| GET        | `/products/{id}`   | —           | Detalle de un producto            |
| POST       | `/products`        | JWT + admin | Crear producto                    |
| PUT/PATCH  | `/products/{id}`   | JWT + admin | Actualizar producto               |
| DELETE     | `/products/{id}`   | JWT + admin | Eliminar producto (409 si tiene órdenes) |

### Órdenes

| Método | Ruta              | Auth | Descripción                                   |
|--------|-------------------|------|-----------------------------------------------|
| GET    | `/orders`         | JWT  | Historial de compras del cliente (con detalle y pago) |
| POST   | `/orders`         | JWT  | Crear orden a partir de `items[]` (reserva stock) |
| GET    | `/orders/{id}`    | JWT  | Detalle de una orden propia                   |

### Pagos (Stripe)

| Método | Ruta                          | Auth | Descripción                                          |
|--------|-------------------------------|------|-----------------------------------------------------|
| POST   | `/orders/{id}/payments`       | JWT  | Crear `PaymentIntent` para la orden (`client_secret`) |
| POST   | `/payments/{id}/confirm`      | JWT  | Confirmar el pago y finalizar la compra             |
| GET    | `/payments`                   | JWT  | Historial de transacciones del cliente             |
| GET    | `/payments/{id}`              | JWT  | Detalle de una transacción propia                  |

---

## Flujo de compra de ejemplo (cURL)

```bash
BASE=http://localhost:8000/api

# 1. Registro (o login con cliente@example.com / Password123)
TOKEN=$(curl -s -X POST $BASE/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"cliente@example.com","password":"Password123"}' | jq -r .access_token)

# 2. Ver catálogo
curl -s "$BASE/products?per_page=5" | jq

# 3. Crear una orden con 2 productos
ORDER=$(curl -s -X POST $BASE/orders \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"items":[{"product_id":1,"quantity":2},{"product_id":3,"quantity":1}]}')
ORDER_ID=$(echo "$ORDER" | jq -r .data.id)

# 4. Iniciar el pago (crea el PaymentIntent en Stripe)
PAY=$(curl -s -X POST $BASE/orders/$ORDER_ID/payments -H "Authorization: Bearer $TOKEN")
PAY_ID=$(echo "$PAY" | jq -r .data.id)
echo "$PAY" | jq   # contiene stripe_client_secret

# 5. Confirmar el pago
#    pm_card_visa            -> éxito
#    pm_card_chargeDeclined  -> rechazo (HTTP 402), la orden queda "pending" para reintento
curl -s -X POST $BASE/payments/$PAY_ID/confirm \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"payment_method":"pm_card_visa"}' | jq

# 6. Historial de compras
curl -s "$BASE/orders" -H "Authorization: Bearer $TOKEN" | jq
```

---

## Integración con Stripe

La lógica está encapsulada en `App\Services\Stripe\StripeService`:

- **Modo real** (hay un `STRIPE_SECRET` válido): usa el SDK oficial
  `stripe/stripe-php` para crear y confirmar `PaymentIntents`
  (`automatic_payment_methods`), guardando `payment_intent_id`, `client_secret`,
  estado y la respuesta completa de la pasarela en la tabla `payments`.
  En una integración real el `client_secret` se entrega al frontend, que
  completa el pago con **Stripe.js / Elements**.
- **Modo simulación** (sin clave o clave de marcador): genera `PaymentIntents`
  locales para poder ejecutar y evaluar todo el flujo sin cuenta de Stripe.
  El resultado de la confirmación depende del `payment_method` enviado
  (`pm_card_visa` → éxito, `pm_card_chargeDeclined` → rechazo).

Reglas de negocio:

- El stock se **reserva** al crear la orden.
- Si el pago se confirma con éxito → la orden pasa a `paid`.
- Si el pago es rechazado → la orden permanece `pending` (stock reservado) y el
  cliente puede reintentar con `POST /orders/{id}/payments`.

---

## Manejo de errores

Todas las respuestas de error son JSON y consistentes
(`bootstrap/app.php` + middleware `ForceJsonResponse`):

| Código | Situación                                    | Cuerpo                                             |
|--------|----------------------------------------------|---------------------------------------------------|
| 401    | Falta token o es inválido                    | `{"message": "Unauthenticated. ..."}`             |
| 403    | Sin permisos (no admin / recurso ajeno)      | `{"message": "..."}`                              |
| 404    | Recurso o ruta inexistente                   | `{"message": "..."}`                              |
| 409    | Conflicto (orden ya pagada, producto con órdenes) | `{"message": "..."}`                          |
| 422    | Validación fallida (Form Request)            | `{"message": "...", "errors": { "campo": [...] }}` |
| 402    | Pago rechazado por la pasarela               | `{"message": "...", "data": { ...payment }}`      |

---

## Pruebas

Suite de pruebas de integración (SQLite en memoria, sin configuración extra):

```bash
php artisan test
```

Cubre: registro/login, protección de rutas, CRUD de productos y autorización por
rol, creación de órdenes, control de stock, flujo completo de pago, rechazo +
reintento y aislamiento de datos entre clientes.

---

## Estructura del proyecto

```
app/
├── Http/
│   ├── Controllers/Api/      AuthController, ProductController, OrderController, PaymentController
│   ├── Middleware/           EnsureUserIsAdmin, ForceJsonResponse
│   ├── Requests/             Form Requests (Auth, Product, Order, Payment)
│   └── Resources/            API Resources (User, Product, Order, OrderItem, Payment)
├── Models/                   User, Product, Order, OrderItem, Payment  (+ esquemas OpenAPI)
└── Services/Stripe/          StripeService (real + simulación)
database/
├── migrations/               users (extendida), products, orders, order_items, payments
├── factories/                UserFactory, ProductFactory
└── seeders/                  DatabaseSeeder, UserSeeder, ProductSeeder
routes/api.php                Definición de todos los endpoints
config/                       auth.php (guard api=jwt), jwt.php, l5-swagger.php, services.php (stripe)
```

---

## Publicar en GitHub

```bash
git init
git add .
git commit -m "API de e-commerce con Laravel 12, JWT, Stripe y Swagger"
git branch -M main
git remote add origin https://github.com/<usuario>/<repo>.git
git push -u origin main
```

> El archivo `.env` está en `.gitignore` (nunca subas credenciales).
> `.env.example` documenta todas las variables necesarias.
