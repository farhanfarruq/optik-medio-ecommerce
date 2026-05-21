<?php

namespace Tests\Feature;

use App\Models\PrescriptionProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrescriptionProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_update_default_and_delete_prescription_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'label' => 'Resep Utama',
            'lens_type' => 'progressive',
            'right_sphere' => -1.25,
            'right_cylinder' => -0.50,
            'right_axis' => 90,
            'right_add' => 1.00,
            'left_sphere' => -1.00,
            'left_cylinder' => -0.25,
            'left_axis' => 85,
            'left_add' => 1.00,
            'pd_right' => 31,
            'pd_left' => 32,
            'is_default' => true,
        ];

        $profileId = $this->postJson('/api/prescriptions', $payload)
            ->assertCreated()
            ->assertJsonPath('label', 'Resep Utama')
            ->assertJsonPath('is_default', true)
            ->json('id');

        $this->getJson('/api/prescriptions')
            ->assertOk()
            ->assertJsonPath('0.id', $profileId);

        $this->putJson("/api/prescriptions/{$profileId}", [
            ...$payload,
            'label' => 'Resep Update',
            'is_default' => false,
        ])
            ->assertOk()
            ->assertJsonPath('label', 'Resep Update');

        $this->postJson("/api/prescriptions/{$profileId}/set-default")
            ->assertOk()
            ->assertJsonPath('is_default', true);

        $this->deleteJson("/api/prescriptions/{$profileId}")
            ->assertOk();

        $this->assertDatabaseMissing('prescription_profiles', ['id' => $profileId]);
    }

    public function test_prescription_profiles_are_owned_by_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $profile = PrescriptionProfile::create([
            'user_id' => $owner->id,
            'label' => 'Private',
            'lens_type' => 'single_vision',
            'right_sphere' => -1,
            'left_sphere' => -1,
            'pd_single' => 63,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/prescriptions/{$profile->id}")->assertNotFound();
        $this->deleteJson("/api/prescriptions/{$profile->id}")->assertNotFound();
    }

    public function test_prescription_profile_rejects_invalid_optical_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/prescriptions', [
            'label' => 'Invalid Axis',
            'lens_type' => 'single_vision',
            'right_cylinder' => -0.50,
            'left_sphere' => -1,
            'pd_single' => 63,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['right_axis']);

        $this->postJson('/api/prescriptions', [
            'label' => 'Invalid Add',
            'lens_type' => 'single_vision',
            'right_sphere' => -1,
            'left_sphere' => -1,
            'right_add' => 1.00,
            'pd_single' => 63,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lens_type']);
    }
}
