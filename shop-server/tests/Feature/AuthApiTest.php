<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    config([
        'mshop.auth.login_max_attempts' => 5,
        'mshop.auth.login_decay_minutes' => 15,
        'mshop.auth.token_ttl_days' => 30,
    ]);
});

it('registers a customer and returns a token', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Aung Aung',
        'email' => 'aung@example.test',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonPath('user.status', 'active')
        ->assertJsonPath('user.locale', 'my')
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'name']]);
});

it('rejects duplicate email with email_taken', function (): void {
    User::factory()->create(['email' => 'aung@example.test']);

    $this->postJson('/api/auth/register', [
        'name' => 'Aung',
        'email' => 'Aung@example.test',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'email_taken');
});

it('ignores role tampering on register', function (): void {
    $this->postJson('/api/auth/register', [
        'name' => 'Aung',
        'email' => 'tamper@example.test',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'role' => 'admin',
    ])->assertCreated()
        ->assertJsonPath('user.role', 'customer');
});

it('logs in and enforces password rules', function (): void {
    $user = User::factory()->create([
        'email' => 'login@example.test',
        'password' => Hash::make('secret-pass-1'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'login@example.test',
        'password' => 'wrong-pass-1',
    ])->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');

    $ok = $this->postJson('/api/auth/login', [
        'email' => 'login@example.test',
        'password' => 'secret-pass-1',
        'device_name' => 'chrome',
    ])->assertOk();

    $token = PersonalAccessToken::findToken($ok->json('token'));
    expect($token)->not->toBeNull()
        ->and($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->greaterThan(now()->addDays(29)))->toBeTrue();

    $this->postJson('/api/auth/register', [
        'name' => 'Weak',
        'email' => 'weak@example.test',
        'password' => 'abcdefgh',
        'password_confirmation' => 'abcdefgh',
    ])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
});

it('logs out current token and logout-all revokes all', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret-pass-1')]);

    $tokenA = $user->createToken('a', ['*'], now()->addDays(30))->plainTextToken;
    $tokenB = $user->createToken('b', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($tokenA)->postJson('/api/auth/logout')->assertNoContent();
    $this->withToken($tokenA)->getJson('/api/auth/me')->assertStatus(401);
    $this->withToken($tokenB)->getJson('/api/auth/me')->assertOk();

    $this->withToken($tokenB)->postJson('/api/auth/logout-all')->assertNoContent();
    $this->withToken($tokenB)->getJson('/api/auth/me')->assertStatus(401);
});

it('handles forgot and reset password', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.test', 'password' => Hash::make('old-pass-1')]);
    $oldToken = $user->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.test'])
        ->assertStatus(202);

    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.test'])
        ->assertStatus(202);

    Notification::assertSentTo($user, ResetPassword::class);

    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'reset@example.test',
        'password' => 'new-secret-1',
        'password_confirmation' => 'new-secret-1',
    ])->assertOk();

    $this->withToken($oldToken)->getJson('/api/auth/me')->assertStatus(401);

    $this->postJson('/api/auth/login', [
        'email' => 'reset@example.test',
        'password' => 'new-secret-1',
    ])->assertOk();
});

it('updates locale via patch me locale', function (): void {
    $user = User::factory()->create(['locale' => 'my']);
    $token = $user->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)->patchJson('/api/me/locale', ['locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('user.locale', 'en');

    $this->withToken($token)->patchJson('/api/me/locale', ['locale' => 'fr'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');
});

it('rate limits failed logins', function (): void {
    User::factory()->create([
        'email' => 'limit@example.test',
        'password' => Hash::make('secret-pass-1'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', [
            'email' => 'limit@example.test',
            'password' => 'wrong-pass-1',
        ])->assertStatus(401);
    }

    $this->postJson('/api/auth/login', [
        'email' => 'limit@example.test',
        'password' => 'wrong-pass-1',
    ])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
});

it('blocks login for blocked accounts with 403', function (): void {
    User::factory()->blocked()->create([
        'email' => 'blocked@example.test',
        'password' => Hash::make('secret-pass-1'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'blocked@example.test',
        'password' => 'secret-pass-1',
    ])->assertStatus(403)->assertJsonPath('code', 'account_blocked');
});
