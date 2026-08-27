<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create();
    }

    public function test_a_customer_can_place_an_order_and_stock_is_reserved(): void
    {
        $customer = $this->customer();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10, 'is_active' => true]);

        $response = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', 300)
            ->assertJsonPath('data.total', 339); // 300 + 13% tax

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_an_order_cannot_exceed_available_stock(): void
    {
        $customer = $this->customer();
        $product = Product::factory()->create(['stock' => 2, 'is_active' => true]);

        $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_full_checkout_flow_marks_the_order_as_paid(): void
    {
        $customer = $this->customer();
        $product = Product::factory()->create(['price' => 50, 'stock' => 5, 'is_active' => true]);

        $order = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->json('data');

        $payment = $this->actingAs($customer, 'api')
            ->postJson("/api/orders/{$order['id']}/payments")
            ->assertCreated()
            ->assertJsonPath('data.status', 'requires_confirmation')
            ->json('data');

        $this->actingAs($customer, 'api')
            ->postJson("/api/payments/{$payment['id']}/confirm", ['payment_method' => 'pm_card_visa'])
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('order.status', 'paid');

        $this->assertDatabaseHas('payments', ['id' => $payment['id'], 'status' => 'succeeded']);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'paid']);
    }

    public function test_a_declined_payment_keeps_the_order_pending_for_retry(): void
    {
        $customer = $this->customer();
        $product = Product::factory()->create(['price' => 50, 'stock' => 5, 'is_active' => true]);

        $order = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->json('data');

        $payment = $this->actingAs($customer, 'api')
            ->postJson("/api/orders/{$order['id']}/payments")->json('data');

        $this->actingAs($customer, 'api')
            ->postJson("/api/payments/{$payment['id']}/confirm", ['payment_method' => 'pm_card_chargeDeclined'])
            ->assertStatus(402)
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'pending']);
        $this->assertSame(3, $product->fresh()->stock); // stock stays reserved

        // Retry succeeds.
        $retry = $this->actingAs($customer, 'api')
            ->postJson("/api/orders/{$order['id']}/payments")->json('data');

        $this->actingAs($customer, 'api')
            ->postJson("/api/payments/{$retry['id']}/confirm", ['payment_method' => 'pm_card_visa'])
            ->assertOk()
            ->assertJsonPath('order.status', 'paid');
    }

    public function test_a_customer_only_sees_their_own_orders(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        $product = Product::factory()->create(['stock' => 10, 'is_active' => true]);

        $order = $this->actingAs($alice, 'api')->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->json('data');

        $this->actingAs($bob, 'api')->getJson("/api/orders/{$order['id']}")->assertStatus(403);
        $this->actingAs($alice, 'api')->getJson("/api/orders/{$order['id']}")->assertOk();

        $this->actingAs($bob, 'api')->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
