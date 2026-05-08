<?php

namespace Tests\Feature;

use App\Enums\MembershipAssignmentType;
use App\Models\Commission;
use App\Models\LevelMember;
use App\Models\User;
use App\Models\UserAffiliator;
use App\Models\UserLevelMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MembershipAffiliateMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_and_affiliate_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('level_members'));
        $this->assertTrue(Schema::hasTable('user_level_members'));
        $this->assertTrue(Schema::hasTable('user_affiliators'));
        $this->assertTrue(Schema::hasTable('commissions'));
        $this->assertTrue(Schema::hasTable('commission_details'));

        $this->assertTrue(Schema::hasColumns('users', ['referred_by_affiliator_id']));
        $this->assertTrue(Schema::hasColumns('level_members', ['name', 'slug', 'min_points', 'discount_percentage']));
        $this->assertTrue(Schema::hasColumns('user_level_members', ['assignment_type', 'effective_from', 'effective_until']));
        $this->assertTrue(Schema::hasColumns('user_affiliators', ['affiliate_code', 'status', 'commission_rate_percentage']));
        $this->assertTrue(Schema::hasColumns('commissions', ['request_no', 'requested_amount', 'approved_amount']));
        $this->assertTrue(Schema::hasColumns('commission_details', ['base_amount', 'commission_rate_percentage', 'commission_amount']));
    }

    public function test_user_affiliator_requires_unique_user_and_affiliate_code(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        UserAffiliator::create([
            'user_id' => $user->id,
            'affiliate_code' => 'AFF-001',
            'commission_rate_percentage' => 10,
        ]);

        $this->expectException(QueryException::class);

        UserAffiliator::create([
            'user_id' => $otherUser->id,
            'affiliate_code' => 'AFF-001',
            'commission_rate_percentage' => 12.5,
        ]);
    }

    public function test_user_affiliator_requires_one_profile_per_user(): void
    {
        $user = User::factory()->create();

        UserAffiliator::create([
            'user_id' => $user->id,
            'affiliate_code' => 'AFF-ONE',
            'commission_rate_percentage' => 10,
        ]);

        $this->expectException(QueryException::class);

        UserAffiliator::create([
            'user_id' => $user->id,
            'affiliate_code' => 'AFF-TWO',
            'commission_rate_percentage' => 12,
        ]);
    }

    public function test_only_one_active_membership_can_exist_per_user(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $basic = LevelMember::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'min_points' => 0,
            'discount_percentage' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $gold = LevelMember::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'min_points' => 100,
            'discount_percentage' => 5,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        UserLevelMember::create([
            'user_id' => $user->id,
            'level_member_id' => $basic->id,
            'points_snapshot' => 10,
            'assignment_type' => MembershipAssignmentType::Auto,
            'assigned_by' => $admin->id,
        ]);

        $this->expectException(QueryException::class);

        UserLevelMember::create([
            'user_id' => $user->id,
            'level_member_id' => $gold->id,
            'points_snapshot' => 120,
            'assignment_type' => MembershipAssignmentType::Manual,
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_historical_membership_rows_are_allowed_when_effective_until_is_filled(): void
    {
        $user = User::factory()->create();
        $level = LevelMember::create([
            'name' => 'Silver',
            'slug' => 'silver',
            'min_points' => 50,
            'discount_percentage' => 3,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        UserLevelMember::create([
            'user_id' => $user->id,
            'level_member_id' => $level->id,
            'points_snapshot' => 50,
            'effective_from' => now()->subMonth(),
            'effective_until' => now()->subDay(),
        ]);

        UserLevelMember::create([
            'user_id' => $user->id,
            'level_member_id' => $level->id,
            'points_snapshot' => 80,
            'effective_from' => now(),
        ]);

        $this->assertSame(2, UserLevelMember::where('user_id', $user->id)->count());
    }

    public function test_referred_by_affiliator_must_reference_existing_affiliate_profile(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $user->update([
            'referred_by_affiliator_id' => 999999,
        ]);
    }

    public function test_commission_request_number_uses_medio_format(): void
    {
        Carbon::setTestNow('2026-05-07 08:00:00');

        $user = User::factory()->create();
        $affiliator = UserAffiliator::create([
            'user_id' => $user->id,
            'affiliate_code' => 'AFF-COM',
            'status' => 'approved',
            'commission_rate_percentage' => 10,
        ]);

        $first = Commission::create([
            'user_affiliator_id' => $affiliator->id,
            'requested_amount' => 100000,
        ]);

        $second = Commission::create([
            'user_affiliator_id' => $affiliator->id,
            'requested_amount' => 125000,
        ]);

        $this->assertSame('COM/2026/000001', $first->request_no);
        $this->assertSame('COM/2026/000002', $second->request_no);

        Carbon::setTestNow();
    }
}
