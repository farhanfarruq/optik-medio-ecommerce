<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BranchSchedule;
use App\Models\StoreBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function createBranch(array $overrides = []): StoreBranch
    {
        return StoreBranch::create(array_merge([
            'name'                 => 'Cabang Test',
            'code'                 => 'CBG-' . str()->random(4),
            'address'              => 'Jl. Test No. 1',
            'city'                 => 'Jakarta',
            'province'             => 'DKI Jakarta',
            'appointment_capacity' => 5,
            'is_active'            => true,
        ], $overrides));
    }

    public function test_public_can_get_branches(): void
    {
        $this->createBranch();
        $this->createBranch(['is_active' => false]);

        $this->getJson('/api/branches')
            ->assertOk()
            ->assertJsonCount(1); // hanya yang aktif
    }

    public function test_public_can_check_branch_availability(): void
    {
        $branch = $this->createBranch(['appointment_capacity' => 3]);
        $date   = now()->addDay()->toDateString();

        $this->getJson("/api/branches/{$branch->id}/availability?date={$date}")
            ->assertOk()
            ->assertJsonStructure(['branch_id', 'date', 'capacity', 'available', 'available_slots', 'is_closed'])
            ->assertJsonPath('available', 3);
    }

    public function test_availability_decreases_after_booking(): void
    {
        $user   = User::factory()->create();
        $branch = $this->createBranch(['appointment_capacity' => 3]);
        $date   = now()->addDay()->toDateString();

        // Buat 1 appointment
        Appointment::create([
            'appointment_number' => 'APT-TEST001',
            'user_id'            => $user->id,
            'branch_id'          => $branch->id,
            'appointment_date'   => $date,
            'appointment_time'   => '10:00:00',
            'service_type'       => 'eye_test',
            'status'             => 'confirmed',
            'customer_name'      => $user->name,
            'customer_phone'     => '081234567890',
        ]);

        $this->getJson("/api/branches/{$branch->id}/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('available', 2); // 3 - 1 = 2
    }

    public function test_branch_is_closed_when_capacity_full(): void
    {
        $user   = User::factory()->create();
        $branch = $this->createBranch(['appointment_capacity' => 1]);
        $date   = now()->addDay()->toDateString();

        Appointment::create([
            'appointment_number' => 'APT-FULL001',
            'user_id'            => $user->id,
            'branch_id'          => $branch->id,
            'appointment_date'   => $date,
            'appointment_time'   => '09:00:00',
            'service_type'       => 'eye_test',
            'status'             => 'confirmed',
            'customer_name'      => $user->name,
            'customer_phone'     => '081234567890',
        ]);

        $this->getJson("/api/branches/{$branch->id}/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('is_closed', true)
            ->assertJsonPath('available', 0);
    }

    public function test_authenticated_user_can_create_appointment(): void
    {
        $user   = User::factory()->create();
        $branch = $this->createBranch();
        $date   = now()->addDay()->toDateString();

        $this->actingAs($user)
            ->postJson('/api/appointments', [
                'branch_id'        => $branch->id,
                'appointment_date' => $date,
                'appointment_time' => '10:00',
                'service_type'     => 'eye_test',
                'customer_name'    => $user->name,
                'customer_phone'   => '081234567890',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'appointment']);

        $this->assertDatabaseHas('appointments', [
            'user_id'   => $user->id,
            'branch_id' => $branch->id,
            'status'    => 'pending',
        ]);
    }

    public function test_appointment_rejected_when_capacity_full(): void
    {
        $user   = User::factory()->create();
        $branch = $this->createBranch(['appointment_capacity' => 1]);
        $date   = now()->addDay()->toDateString();

        // Isi kapasitas
        Appointment::create([
            'appointment_number' => 'APT-FULL002',
            'user_id'            => $user->id,
            'branch_id'          => $branch->id,
            'appointment_date'   => $date,
            'appointment_time'   => '09:00:00',
            'service_type'       => 'eye_test',
            'status'             => 'confirmed',
            'customer_name'      => $user->name,
            'customer_phone'     => '081234567890',
        ]);

        $user2 = User::factory()->create();
        $this->actingAs($user2)
            ->postJson('/api/appointments', [
                'branch_id'        => $branch->id,
                'appointment_date' => $date,
                'appointment_time' => '10:00',
                'service_type'     => 'fitting',
                'customer_name'    => $user2->name,
                'customer_phone'   => '081234567891',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Slot untuk tanggal ini sudah penuh. Pilih tanggal lain.');
    }

    public function test_user_can_cancel_own_appointment(): void
    {
        $user   = User::factory()->create();
        $branch = $this->createBranch();

        $apt = Appointment::create([
            'appointment_number' => 'APT-CANCEL01',
            'user_id'            => $user->id,
            'branch_id'          => $branch->id,
            'appointment_date'   => now()->addDay()->toDateString(),
            'appointment_time'   => '11:00:00',
            'service_type'       => 'consultation',
            'status'             => 'pending',
            'customer_name'      => $user->name,
            'customer_phone'     => '081234567890',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/appointments/{$apt->id}")
            ->assertOk();

        $this->assertDatabaseHas('appointments', ['id' => $apt->id, 'status' => 'cancelled']);
    }

    public function test_user_cannot_cancel_completed_appointment(): void
    {
        $user   = User::factory()->create();
        $branch = $this->createBranch();

        $apt = Appointment::create([
            'appointment_number' => 'APT-DONE01',
            'user_id'            => $user->id,
            'branch_id'          => $branch->id,
            'appointment_date'   => now()->subDay()->toDateString(),
            'appointment_time'   => '10:00:00',
            'service_type'       => 'eye_test',
            'status'             => 'completed',
            'customer_name'      => $user->name,
            'customer_phone'     => '081234567890',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/appointments/{$apt->id}")
            ->assertStatus(422);
    }

    public function test_branch_schedule_override_capacity(): void
    {
        $branch = $this->createBranch(['appointment_capacity' => 10]);
        $date   = now()->addDay();

        // Override kapasitas jadi 2
        BranchSchedule::create([
            'branch_id'         => $branch->id,
            'date'              => $date->toDateString(),
            'capacity_override' => 2,
            'is_closed'         => false,
        ]);

        $this->getJson("/api/branches/{$branch->id}/availability?date={$date->toDateString()}")
            ->assertOk()
            ->assertJsonPath('capacity', 10) // default capacity
            ->assertJsonPath('available', 2); // override
    }

    public function test_branch_schedule_can_mark_day_closed(): void
    {
        $branch = $this->createBranch(['appointment_capacity' => 10]);
        $date   = now()->addDay();

        BranchSchedule::create([
            'branch_id' => $branch->id,
            'date'      => $date->toDateString(),
            'is_closed' => true,
        ]);

        $this->getJson("/api/branches/{$branch->id}/availability?date={$date->toDateString()}")
            ->assertOk()
            ->assertJsonPath('is_closed', true)
            ->assertJsonPath('available', 0);
    }
}
