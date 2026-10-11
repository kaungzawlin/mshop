<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'max:255', PasswordRule::min(8)->letters()->numbers()],
            'locale' => ['sometimes', 'in:my,en'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'This email is already registered.',
                'code' => 'email_taken',
                'errors' => ['email' => ['This email is already registered.']],
            ], 422);
        }

        $user = new User;
        $user->name = trim($validated['name']);
        $user->email = $email;
        $user->password = $validated['password'];
        $user->locale = $validated['locale'] ?? 'my';
        $user->role = Role::Customer;
        $user->status = UserStatus::Active;
        $user->save();

        $token = $this->createToken($user, 'web');

        return response()->json([
            'token' => $token,
            'user' => (new UserResource($user))->resolve(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $throttleKey = Str::transliterate(Str::lower($email).'|'.$request->ip());
        $maxAttempts = (int) config('mshop.auth.login_max_attempts', 5);
        $decayMinutes = (int) config('mshop.auth.login_decay_minutes', 15);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Too many login attempts. Please try again later.',
                'code' => 'too_many_requests',
            ], 429)->withHeaders([
                'Retry-After' => (string) $seconds,
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, $decayMinutes * 60);

            return response()->json([
                'message' => 'Email or password is incorrect.',
                'code' => 'invalid_credentials',
            ], 401);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'message' => 'This account has been blocked. Contact support.',
                'code' => 'account_blocked',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $deviceName = $validated['device_name'] ?? 'web';
        $token = $this->createToken($user, $deviceName);

        return response()->json([
            'token' => $token,
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    public function logoutAll(Request $request): Response
    {
        $request->user()?->tokens()->delete();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => (new UserResource($request->user()))->resolve(),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (array_key_exists('name', $validated)) {
            $user->name = trim($validated['name']);
            $user->save();
        }

        return response()->json([
            'user' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim($request->string('email')->toString()));

        Password::sendResetLink(['email' => $email]);

        return response()->json([
            'message' => 'If that email has an account, a reset link has been sent.',
        ], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'max:255', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($validated['email'])),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This reset link is invalid or has expired.',
                'code' => 'invalid_reset_token',
                'errors' => ['email' => ['This reset link is invalid or has expired.']],
            ], 422);
        }

        return response()->json([
            'message' => 'Password has been reset. Please log in.',
        ]);
    }

    private function createToken(User $user, string $deviceName): string
    {
        $ttlDays = (int) config('mshop.auth.token_ttl_days', 30);

        return $user->createToken(
            $deviceName,
            ['*'],
            now()->addDays($ttlDays)
        )->plainTextToken;
    }
}
