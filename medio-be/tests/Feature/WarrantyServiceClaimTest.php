<?php

namespace Tests\Feature;

use App\Models\ServiceClaim;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyServiceClaimTest extends TestCase
{
    use RefreshDatabase;

    private function createWarranty(User $user, array $overrides = []): Warranty
    {
        return Warranty::create(array_merge([
            'warranty_number'     => Warranty::generateNumber(),
            'user_id'             => $user->id,
            'product_name'        => 'Frame Test Premium',
            'purchase_date'       => now()->subMonths(3)->toDateString(),
            'warranty_expires_at' => now()->addMonths(9)->toDateString(),
            'warranty_months'     => 12,
            'status'              => 'active',
        ], $overrides));
    }

    public function test_user_can_get_own_warranties(): void
    {
        $user = User::factory()->create();
        $this->createWarranty($user);
        $this->createWarranty($user);

        $this->actingAs($user)
            ->getJson('/api/warranties')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_user_cannot_see_other_user_warranties(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->createWarranty($user1);

        $this->actingAs($user2)
            ->getJson('/api/warranties')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_warranty_detail_shows_days_remaining(): void
    {
        $user     = User::factory()->create();
        $warranty = $this->createWarranty($user);

        $this->actingAs($user)
            ->getJson("/api/warranties/{$warranty->id}")
            ->assertOk()
            ->assertJsonStructure(['warranty', 'days_remaining', 'is_active'])
            ->assertJsonPath('is_active', true);
    }

    public function test_expired_warranty_is_not_active(): void
    {
        $user     = User::factory()->create();
        $warranty = $this->createWarranty($user, [
            'warranty_expires_at' => now()->subDay()->toDateString(),
            'status'              => 'expired',
        ]);

        $this->assertFalse($warranty->isActive());
        $this->assertSame(0, $warranty->daysRemaining());
    }

    public function test_user_can_submit_service_claim(): void
    {
        $user     = User::factory()->create();
        $warranty = $this->createWarranty($user);

        $this->actingAs($user)
            ->postJson('/api/service-claims', [
                'warranty_id' => $warranty->id,
                'claim_type'  => 'warranty_repair',
                'description' => 'Frame patah di engsel kiri.',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'claim']);

        $this->assertDatabaseHas('service_claims', [
            'user_id'                => $user->id,
            'warranty_id'            => $warranty->id,
            'claim_type'             => 'warranty_repair',
            'status'                 => 'submitted',
            'is_covered_by_warranty' => true,
        ]);
    }

    public function test_user_cannot_claim_warranty_of_other_user(): void
    {
        $user1    = User::factory()->create();
        $user2    = User::factory()->create();
        $warranty = $this->createWarranty($user1);

        $this->actingAs($user2)
            ->postJson('/api/service-claims', [
                'warranty_id' => $warranty->id,
                'claim_type'  => 'warranty_repair',
                'description' => 'Mencoba klaim garansi orang lain.',
            ])
            ->assertStatus(404);
    }

    public function test_service_claim_without_warranty_is_allowed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/service-claims', [
                'claim_type'  => 'cleaning',
                'description' => 'Minta pembersihan frame.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('claim.is_covered_by_warranty', false);
    }

    public function test_warranty_number_is_unique(): void
    {
        $num1 = Warranty::generateNumber();
        $num2 = Warranty::generateNumber();
        $this->assertNotSame($num1, $num2);
        $this->assertStringStartsWith('WRT-', $num1);
    }

    public function test_service_claim_number_is_unique(): void
    {
        $num1 = ServiceClaim::generateNumber();
        $num2 = ServiceClaim::generateNumber();
        $this->assertNotSame($num1, $num2);
        $this->assertStringStartsWith('SVC-', $num1);
    }
}
