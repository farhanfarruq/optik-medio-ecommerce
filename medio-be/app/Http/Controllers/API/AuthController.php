<?php

namespace App\Http\Controllers\API;

use App\Enums\UserAffiliatorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserAffiliator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register — buat user + kirim OTP ke email
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $referringAffiliator = null;
        if ($request->filled('referral_code')) {
            $referralCode = Str::upper($request->string('referral_code')->trim()->toString());
            $referringAffiliator = UserAffiliator::query()
                ->where('affiliate_code', $referralCode)
                ->where('status', UserAffiliatorStatus::Approved->value)
                ->first();

            if (! $referringAffiliator) {
                throw ValidationException::withMessages([
                    'referral_code' => 'Kode affiliator tidak valid atau belum aktif.',
                ]);
            }
        }

        $user = DB::transaction(function () use ($request, $referringAffiliator) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'referred_by_affiliator_id' => $referringAffiliator?->id,
            ]);

            if ($request->boolean('register_as_affiliator')) {
                UserAffiliator::create([
                    'user_id' => $user->id,
                    'affiliate_code' => $this->generateAffiliateCode($user->name),
                    'status' => UserAffiliatorStatus::Pending,
                    'commission_rate_percentage' => 5,
                ]);
            }

            return $user;
        });

        // Generate & kirim OTP
        $this->generateAndSendOtp($user, 'email');

        return response()->json([
            'message' => 'Registrasi berhasil! Kode verifikasi telah dikirim ke email Anda.',
            'email'   => $user->email,
        ], 201);
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $limiterKey = $this->otpAttemptLimiterKey($request);

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);

            return response()->json([
                'message' => 'Terlalu banyak percobaan OTP. Silakan coba lagi nanti.',
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            RateLimiter::hit($limiterKey, 600);

            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // Cari OTP yang valid (belum expired, belum diverifikasi)
        $otp = OtpCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('type', 'email')
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            RateLimiter::hit($limiterKey, 600);

            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        // Tandai OTP sebagai terverifikasi
        $otp->update(['verified_at' => now()]);

        // Tandai email user sebagai terverifikasi
        $user->update(['email_verified_at' => now()]);

        RateLimiter::clear($limiterKey);

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->load('addresses');

        return response()->json([
            'message' => 'Email berhasil diverifikasi!',
            'user'    => $user,
        ]);
    }

    /**
     * Kirim ulang OTP
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // Rate limiting: maks 3 OTP dalam 10 menit terakhir
        $recentCount = OtpCode::where('user_id', $user->id)
            ->where('type', 'email')
            ->where('created_at', '>', now()->subMinutes(10))
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'message' => 'Terlalu banyak permintaan. Silakan tunggu beberapa menit.',
            ], 429);
        }

        $this->generateAndSendOtp($user, 'email');

        return response()->json([
            'message' => 'Kode verifikasi baru telah dikirim ke email Anda.',
        ]);
    }

    /**
     * Login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak valid.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Cek apakah email sudah diverifikasi (registrasi pertama kali)
        if (!$user->email_verified_at) {
            $this->generateAndSendOtp($user, 'email');

            return response()->json([
                'message'      => 'Email belum diverifikasi. Kode OTP telah dikirim.',
                'requires_otp' => true,
                'email'        => $user->email,
            ], 403);
        }

        // Setiap login selalu wajib verifikasi OTP
        $this->generateAndSendOtp($user, 'email');

        return response()->json([
            'message'      => 'Kode OTP telah dikirim ke email Anda.',
            'requires_otp' => true,
            'email'        => $user->email,
        ], 403);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Berhasil logout']);
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load([
                'addresses',
                'currentLevelMembership.levelMember',
                'affiliateProfile',
                'referredByAffiliator.user',
            ])
        );
    }

    // ─── Helper ───────────────────────────────────────────────

    /**
     * Generate OTP 6 digit dan kirim via email
     */
    private function generateAndSendOtp(User $user, string $type = 'email'): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($type === 'email') {
            try {
                Mail::to($user->email)->send(new OtpMail($code, $user->name));
            } catch (\Exception $e) {
                Log::error('Failed to send OTP email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'exception' => $e->getMessage(),
                ]);
                if (app()->isLocal()) {
                    Log::warning('OTP email delivery failed in local environment.', [
                        'email' => $user->email,
                        'otp_preview' => str_repeat('*', 4) . substr($code, -2),
                    ]);
                }
            }
        }
    }

    private function otpAttemptLimiterKey(Request $request): string
    {
        return sprintf(
            'auth:verify-otp:%s|%s',
            Str::lower($request->string('email')->trim()->toString()),
            $request->ip(),
        );
    }

    private function generateAffiliateCode(string $name): string
    {
        $base = Str::of($name)
            ->upper()
            ->ascii()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 6)
            ->value();

        if ($base === '') {
            $base = 'MEDIO';
        }

        do {
            $code = $base . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (UserAffiliator::query()->where('affiliate_code', $code)->exists());

        return $code;
    }
}
