<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        Schema::dropIfExists('shipping_addresses');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('code', 6);
            $table->string('type')->default('email');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('province');
            $table->string('province_id');
            $table->string('city');
            $table->string('city_id');
            $table->string('district');
            $table->string('district_id')->nullable();
            $table->string('postal_code');
            $table->text('address');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('shipping_addresses');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_login_uses_session_auth_without_returning_token(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_otp_is_rate_limited_after_repeated_failures(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->unverified()->create();

        OtpCode::create([
            'user_id' => $user->id,
            'code' => '654321',
            'type' => 'email',
            'expires_at' => now()->addMinutes(10),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/verify-otp', [
                'email' => $user->email,
                'code' => '111111',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => '111111',
        ])->assertStatus(429);
    }
}
