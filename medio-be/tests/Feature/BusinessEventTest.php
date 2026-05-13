<?php

namespace Tests\Feature;

use App\Models\BusinessEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_can_post_allowed_event(): void
    {
        $this->postJson('/api/events', [
            'event_type' => BusinessEvent::PRODUCT_VIEWED,
            'payload'    => ['product_id' => 1, 'slug' => 'frame-test'],
            'session_id' => 'test-session-123',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('business_events', [
            'event_type' => BusinessEvent::PRODUCT_VIEWED,
        ]);
    }

    public function test_frontend_cannot_post_disallowed_event(): void
    {
        $this->postJson('/api/events', [
            'event_type' => 'order_created', // tidak boleh dari frontend
            'payload'    => [],
        ])->assertStatus(422);
    }

    public function test_authenticated_user_event_records_user_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/events', [
                'event_type' => BusinessEvent::ADD_TO_CART,
                'payload'    => ['product_id' => 5, 'quantity' => 1],
            ])
            ->assertOk();

        $this->assertDatabaseHas('business_events', [
            'event_type' => BusinessEvent::ADD_TO_CART,
            'user_id'    => $user->id,
        ]);
    }

    public function test_business_event_record_static_method_does_not_throw(): void
    {
        // Harus tidak throw meski ada error
        BusinessEvent::record(
            eventType: BusinessEvent::CHECKOUT_STARTED,
            payload: ['item_count' => 3, 'subtotal' => 450000],
        );

        $this->assertDatabaseHas('business_events', [
            'event_type' => BusinessEvent::CHECKOUT_STARTED,
        ]);
    }

    public function test_search_no_result_event_is_tracked(): void
    {
        $this->postJson('/api/events', [
            'event_type' => BusinessEvent::SEARCH_NO_RESULT,
            'payload'    => ['query' => 'kacamata anti radiasi'],
        ])->assertOk();

        $this->assertDatabaseHas('business_events', [
            'event_type' => BusinessEvent::SEARCH_NO_RESULT,
        ]);
    }

    public function test_checkout_failed_event_is_tracked(): void
    {
        $this->postJson('/api/events', [
            'event_type' => BusinessEvent::CHECKOUT_FAILED,
            'payload'    => ['reason' => 'stock_changed', 'detail' => 'Stok produk berubah'],
        ])->assertOk();

        $this->assertDatabaseHas('business_events', [
            'event_type' => BusinessEvent::CHECKOUT_FAILED,
        ]);
    }

    public function test_event_type_constants_are_defined(): void
    {
        $this->assertSame('product_viewed', BusinessEvent::PRODUCT_VIEWED);
        $this->assertSame('add_to_cart', BusinessEvent::ADD_TO_CART);
        $this->assertSame('checkout_started', BusinessEvent::CHECKOUT_STARTED);
        $this->assertSame('order_created', BusinessEvent::ORDER_CREATED);
        $this->assertSame('payment_success', BusinessEvent::PAYMENT_SUCCESS);
        $this->assertSame('search_no_result', BusinessEvent::SEARCH_NO_RESULT);
        $this->assertSame('checkout_failed', BusinessEvent::CHECKOUT_FAILED);
    }
}
