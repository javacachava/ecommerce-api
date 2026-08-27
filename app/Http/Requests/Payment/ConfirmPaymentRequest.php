<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Test payment method token. Use "pm_card_chargeDeclined" to force a failure.
            'payment_method' => ['nullable', 'string', 'max:120'],
        ];
    }
}
