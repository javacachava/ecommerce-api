<?php

namespace App\Services\Stripe;

use App\Models\Order;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Throwable;

/**
 * Thin wrapper around stripe/stripe-php.
 *
 * When a real secret key is configured it talks to the Stripe API. When the
 * key is missing or still a placeholder it runs in "simulation" mode so the
 * full checkout flow can be exercised without a Stripe account (useful for
 * local development, automated tests and grading).
 */
class StripeService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secret,
        private readonly string $currency = 'usd',
    ) {
        if (! $this->isSimulated()) {
            $this->client = new StripeClient($this->secret);
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            secret: config('services.stripe.secret'),
            currency: (string) config('services.stripe.currency', 'usd'),
        );
    }

    public function isSimulated(): bool
    {
        $secret = (string) $this->secret;

        if (blank($secret)) {
            return true;
        }

        // Cualquier marcador de posicion del .env.example activa el modo simulacion.
        if (Str::contains($secret, ['replace_me', 'replace_with', 'xxxx', 'changeme', 'tu_clave', 'aqui', 'your_'])) {
            return true;
        }

        // Solo una clave secreta con formato real de Stripe desactiva la simulacion.
        return ! Str::isMatch('/^sk_(test|live)_[A-Za-z0-9]{16,}$/', $secret);
    }

    /**
     * Create a PaymentIntent for the given order.
     *
     * @return array{id:string, client_secret:string, status:string, amount:int, currency:string, raw:array<string,mixed>}
     */
    public function createPaymentIntent(Order $order): array
    {
        $amount = $this->toMinorUnits((float) $order->total);
        $currency = $order->currency ?: $this->currency;

        if ($this->isSimulated()) {
            $id = 'pi_sim_'.Str::lower(Str::random(24));

            return [
                'id' => $id,
                'client_secret' => $id.'_secret_'.Str::lower(Str::random(16)),
                'status' => 'requires_confirmation',
                'amount' => $amount,
                'currency' => $currency,
                'raw' => ['simulated' => true],
            ];
        }

        $intent = $this->client->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_reference' => $order->reference,
                'user_id' => (string) $order->user_id,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return [
            'id' => $intent->id,
            'client_secret' => (string) $intent->client_secret,
            'status' => $intent->status,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'raw' => $intent->toArray(),
        ];
    }

    /**
     * Confirm a PaymentIntent. In simulation mode the outcome is driven by the
     * test payment method id (Stripe's own test tokens are reused for realism):
     *   - pm_card_visa / null   -> succeeded
     *   - pm_card_chargeDeclined -> failed
     *
     * @return array{id:string, status:string, failure_reason:?string, raw:array<string,mixed>}
     */
    public function confirmPaymentIntent(string $paymentIntentId, ?string $paymentMethod = 'pm_card_visa'): array
    {
        if ($this->isSimulated()) {
            $declined = $paymentMethod === 'pm_card_chargeDeclined';

            return [
                'id' => $paymentIntentId,
                'status' => $declined ? 'failed' : 'succeeded',
                'failure_reason' => $declined ? 'Your card was declined.' : null,
                'raw' => ['simulated' => true, 'payment_method' => $paymentMethod],
            ];
        }

        try {
            $intent = $this->client->paymentIntents->confirm($paymentIntentId, array_filter([
                'payment_method' => $paymentMethod,
                'return_url' => config('app.url'),
            ]));

            return [
                'id' => $intent->id,
                'status' => $intent->status === 'succeeded' ? 'succeeded' : $intent->status,
                'failure_reason' => $intent->last_payment_error->message ?? null,
                'raw' => $intent->toArray(),
            ];
        } catch (Throwable $e) {
            return [
                'id' => $paymentIntentId,
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'raw' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        if ($this->isSimulated()) {
            return ['id' => $paymentIntentId, 'status' => 'succeeded', 'simulated' => true];
        }

        return $this->client->paymentIntents->retrieve($paymentIntentId)->toArray();
    }

    public function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
