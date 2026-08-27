<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payment',
    title: 'Payment',
    description: 'Registro de una transaccion procesada con Stripe',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'order_id', type: 'integer', example: 10),
        new OA\Property(property: 'gateway', type: 'string', example: 'stripe'),
        new OA\Property(property: 'stripe_payment_intent_id', type: 'string', nullable: true, example: 'pi_3XyZ...'),
        new OA\Property(property: 'stripe_client_secret', type: 'string', nullable: true, example: 'pi_3XyZ..._secret_...'),
        new OA\Property(property: 'status', type: 'string', enum: ['requires_payment_method', 'requires_confirmation', 'processing', 'succeeded', 'failed', 'cancelled'], example: 'succeeded'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 180.57),
        new OA\Property(property: 'currency', type: 'string', example: 'usd'),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'gateway',
        'stripe_payment_intent_id',
        'stripe_client_secret',
        'status',
        'amount',
        'currency',
        'failure_reason',
        'gateway_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
