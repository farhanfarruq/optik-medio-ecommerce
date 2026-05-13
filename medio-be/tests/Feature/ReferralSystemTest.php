<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\ReferralUse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_or_create_referral_code(): void
    {
        $user = User::factory()->create();

        $referral = ReferralCode::getOrCreateForUser($user->id);

        $this->assertNotNull($referral->code);
        $this->assertSame(8, strlen($referral->code));
        $this->assertTrue($referral->is_active);
        $this->assertSame($user->id, $referral->user_id);
    }

    public function test_same_user_always_gets_same_code(): void
    {
        $user = User::factory()->create();

        $code1 = ReferralCode::getOrCreateForUser($user->id)->code;
        $code2 = ReferralCode::getOrCreateForUser($user->id)->code;

        $this->assertSame($code1, $code2);
    }

    public function test_authenticated_user_can_get_my_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/referral/my-code')
            ->assertOk()
            ->assertJsonStructure(['code', 'total_uses', 'reward_inviter', 'reward_invitee', 'share_url']);
    }

    public function test_public_can_validate_referral_code(): void
    {
        $user     = User::factory()->create();
        $referral = ReferralCode::getOrCreateForUser($user->id);

        $this->getJson("/api/referral/validate/{$referral->code}")
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonStructure(['valid', 'inviter_name', 'reward_invitee']);
    }

    public function test_validate_returns_404_for_invalid_code(): void
    {
        $this->getJson('/api/referral/validate/INVALID99')
            ->assertStatus(404)
            ->assertJsonPath('valid', false);
    }

    public function test_user_can_use_referral_code(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $referral = ReferralCode::getOrCreateForUser($inviter->id);

        $this->actingAs($invitee)
            ->postJson('/api/referral/use', ['code' => $referral->code])
            ->assertOk()
            ->assertJsonStructure(['message', 'points_earned']);

        $this->assertDatabaseHas('referral_uses', [
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
        ]);
    }

    public function test_user_cannot_use_own_referral_code(): void
    {
        $user     = User::factory()->create();
        $referral = ReferralCode::getOrCreateForUser($user->id);

        $this->actingAs($user)
            ->postJson('/api/referral/use', ['code' => $referral->code])
            ->assertStatus(422);
    }

    public function test_user_cannot_use_referral_code_twice(): void
    {
        $inviter  = User::factory()->create();
        $invitee  = User::factory()->create();
        $referral = ReferralCode::getOrCreateForUser($inviter->id);

        $this->actingAs($invitee)
            ->postJson('/api/referral/use', ['code' => $referral->code])
            ->assertOk();

        // Coba pakai lagi
        $this->actingAs($invitee)
            ->postJson('/api/referral/use', ['code' => $referral->code])
            ->assertStatus(422);
    }

    public function test_referral_code_use_is_fraud_guarded(): void
    {
        $inviter  = User::factory()->create();
        $invitee  = User::factory()->create();
        $referral = ReferralCode::getOrCreateForUser($inviter->id);

        // Fraud: invitee sama dengan inviter
        $result = ReferralCode::use($referral->code, $inviter->id);
        $this->assertNull($result);
    }

    public function test_referral_code_increments_total_uses(): void
    {
        $inviter  = User::factory()->create();
        $invitee  = User::factory()->create();
        $referral = ReferralCode::getOrCreateForUser($inviter->id);

        $this->actingAs($invitee)
            ->postJson('/api/referral/use', ['code' => $referral->code])
            ->assertOk();

        $this->assertSame(1, $referral->fresh()->total_uses);
    }

    public function test_invitee_receives_loyalty_points_on_use(): void
    {
        $inviter  = User::factory()->create(['loyalty_points' => 0]);
        $invitee  = User::factory()->create(['loyalty_points' => 0]);
        $referral = ReferralCode::getOrCreateForUser($inviter->id);

        $this->actingAs($invitee)
            ->postJson('/api/referral/use', ['code' => $referral->code])
            ->assertOk();

        $expectedPoints = $referral->reward_invitee;
        $this->assertSame($expectedPoints, $invitee->fresh()->loyalty_points);
    }
}
