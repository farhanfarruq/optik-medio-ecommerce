<?php

namespace Tests\Feature;

use App\Services\PrescriptionValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionValidationTest extends TestCase
{
    use RefreshDatabase;

    private PrescriptionValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrescriptionValidationService();
    }

    public function test_valid_prescription_has_no_errors(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-2.00', 'cyl' => '-0.50', 'axis' => '90'],
            'os' => ['sph' => '-1.75', 'cyl' => '-0.25', 'axis' => '85'],
            'pd_single' => '64',
        ]);

        $this->assertEmpty($result['errors']);
        $this->assertTrue($result['is_valid']);
        $this->assertGreaterThan(80, $result['completeness_score']);
    }

    public function test_missing_sphere_produces_error(): void
    {
        $result = $this->service->analyze([
            'od' => ['cyl' => '-0.50', 'axis' => '90'],
            'os' => ['sph' => '-1.75'],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('Sphere kanan', $result['errors'][0]);
    }

    public function test_cylinder_without_axis_produces_error(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-2.00', 'cyl' => '-0.75'],
            'os' => ['sph' => '-1.50'],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Axis kanan', implode(' ', $result['errors']));
    }

    public function test_impossible_sphere_value_produces_error(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-25.00'],
            'os' => ['sph' => '-1.00'],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('di luar rentang normal', $result['errors'][0]);
    }

    public function test_invalid_axis_range_produces_error(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-2.00', 'cyl' => '-0.50', 'axis' => '200'],
            'os' => ['sph' => '-1.50'],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Axis kanan', implode(' ', $result['errors']));
    }

    public function test_high_minus_recommends_high_index_lens(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-8.00'],
            'os' => ['sph' => '-7.50'],
            'pd_single' => '64',
        ]);

        $types = array_column($result['recommendations'], 'type');
        $this->assertContains('high_index', $types);
    }

    public function test_add_value_recommends_progressive_lens(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-1.00', 'add' => '2.00'],
            'os' => ['sph' => '-0.75', 'add' => '2.00'],
            'pd_single' => '62',
        ]);

        $types = array_column($result['recommendations'], 'type');
        $this->assertContains('progressive', $types);
    }

    public function test_missing_pd_produces_warning(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-2.00'],
            'os' => ['sph' => '-1.50'],
        ]);

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('PD', $result['warnings'][0]);
    }

    public function test_completeness_score_is_100_for_complete_prescription(): void
    {
        $result = $this->service->analyze([
            'od' => ['sph' => '-2.00', 'cyl' => '-0.50', 'axis' => '90'],
            'os' => ['sph' => '-1.75', 'cyl' => '-0.25', 'axis' => '85'],
            'pd_single' => '64',
        ]);

        $this->assertSame(100, $result['completeness_score']);
    }

    public function test_api_endpoint_validates_prescription(): void
    {
        $this->postJson('/api/prescriptions/validate', [
            'od' => ['sph' => '-3.00', 'cyl' => '-0.75', 'axis' => '90'],
            'os' => ['sph' => '-2.50', 'cyl' => '-0.50', 'axis' => '85'],
            'pd_single' => '63',
        ])
        ->assertOk()
        ->assertJsonStructure(['errors', 'warnings', 'recommendations', 'completeness_score', 'is_valid'])
        ->assertJsonPath('is_valid', true);
    }
}
