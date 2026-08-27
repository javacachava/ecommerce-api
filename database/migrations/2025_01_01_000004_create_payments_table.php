<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('stripe');
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_client_secret')->nullable();
            $table->enum('status', ['requires_payment_method', 'requires_confirmation', 'processing', 'succeeded', 'failed', 'cancelled'])
                ->default('requires_payment_method');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
