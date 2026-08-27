<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_is_public(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_creating_a_product_requires_authentication(): void
    {
        $this->postJson('/api/products', [])->assertStatus(401);
    }

    public function test_a_customer_cannot_create_products(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'api')
            ->postJson('/api/products', [
                'name' => 'Nuevo', 'sku' => 'SKU-1', 'price' => 10, 'stock' => 5,
            ])
            ->assertStatus(403);
    }

    public function test_an_admin_can_create_update_and_delete_a_product(): void
    {
        $admin = User::factory()->admin()->create();

        $created = $this->actingAs($admin, 'api')->postJson('/api/products', [
            'name' => 'Teclado', 'sku' => 'KB-1', 'price' => 79.9, 'stock' => 10,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('products', ['sku' => 'KB-1', 'is_active' => true]);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/products/{$created['id']}", ['price' => 59.9])
            ->assertOk()
            ->assertJsonPath('data.price', 59.9);

        $this->actingAs($admin, 'api')
            ->deleteJson("/api/products/{$created['id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $created['id']]);
    }

    public function test_product_validation_errors_are_returned_as_json(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'api')
            ->postJson('/api/products', ['name' => '', 'price' => -1, 'stock' => -3])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock']);
    }
}
