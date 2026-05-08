<?php

namespace Tests\Unit;

use App\Enums\CommissionStatus;
use App\Enums\MembershipAssignmentType;
use App\Enums\UserAffiliatorStatus;
use App\Models\Commission;
use App\Models\CommissionDetail;
use App\Models\LevelMember;
use App\Models\User;
use App\Models\UserAffiliator;
use App\Models\UserLevelMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class MembershipAffiliateModelTest extends TestCase
{
    public function test_user_membership_and_affiliate_relationships_are_defined(): void
    {
        $user = new User();

        $this->assertInstanceOf(HasMany::class, $user->levelMemberships());
        $this->assertInstanceOf(HasOne::class, $user->currentLevelMembership());
        $this->assertInstanceOf(HasOne::class, $user->affiliateProfile());
        $this->assertInstanceOf(BelongsTo::class, $user->referredByAffiliator());
    }

    public function test_level_member_and_user_level_member_relationships_are_defined(): void
    {
        $levelMember = new LevelMember();
        $membership = new UserLevelMember();

        $this->assertInstanceOf(HasMany::class, $levelMember->userMemberships());
        $this->assertInstanceOf(BelongsTo::class, $membership->user());
        $this->assertInstanceOf(BelongsTo::class, $membership->levelMember());
        $this->assertInstanceOf(BelongsTo::class, $membership->assignedBy());
    }

    public function test_user_affiliator_and_commission_relationships_are_defined(): void
    {
        $affiliator = new UserAffiliator();
        $commission = new Commission();
        $detail = new CommissionDetail();

        $this->assertInstanceOf(BelongsTo::class, $affiliator->user());
        $this->assertInstanceOf(BelongsTo::class, $affiliator->approvedBy());
        $this->assertInstanceOf(HasMany::class, $affiliator->referrals());
        $this->assertInstanceOf(HasMany::class, $affiliator->commissionRequests());

        $this->assertInstanceOf(BelongsTo::class, $commission->affiliator());
        $this->assertInstanceOf(BelongsTo::class, $commission->processedBy());
        $this->assertInstanceOf(HasMany::class, $commission->details());

        $this->assertInstanceOf(BelongsTo::class, $detail->commission());
        $this->assertInstanceOf(BelongsTo::class, $detail->order());
        $this->assertInstanceOf(BelongsTo::class, $detail->sourceUser());
    }

    public function test_status_and_assignment_casts_use_enums(): void
    {
        $affiliator = new UserAffiliator([
            'status' => UserAffiliatorStatus::Approved,
        ]);
        $membership = new UserLevelMember([
            'assignment_type' => MembershipAssignmentType::Manual,
        ]);
        $commission = new Commission([
            'status' => CommissionStatus::Processing,
        ]);

        $this->assertSame(UserAffiliatorStatus::Approved, $affiliator->status);
        $this->assertSame(MembershipAssignmentType::Manual, $membership->assignment_type);
        $this->assertSame(CommissionStatus::Processing, $commission->status);
    }
}
