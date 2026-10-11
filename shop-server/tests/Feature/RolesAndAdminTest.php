<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleFixturesSeeder;
use Illuminate\Support\Facades\Hash;

it('returns 403 for customer on role-gated routes', function (): void {
    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    foreach (['/api/admin/customers', '/api/reseller/ping', '/api/rider/ping', '/api/ops/ping', '/api/finance/ping'] as $path) {
        $this->withToken($token)->getJson($path)
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden');
    }
});

it('allows each role on its own ping and forbids admin on ops finance', function (): void {
    foreach ([Role::Reseller, Role::Rider, Role::Ops, Role::Finance] as $role) {
        $user = User::factory()->role($role)->create();
        $token = $user->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

        $this->withToken($token)->getJson("/api/{$role->value}/ping")
            ->assertOk()
            ->assertJsonPath('role', $role->value);
    }

    $admin = User::factory()->admin()->create();
    $adminToken = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($adminToken)->getJson('/api/ops/ping')->assertStatus(403);
    $this->withToken($adminToken)->getJson('/api/finance/ping')->assertStatus(403);
});

it('lists customers for admin only and blocks ops', function (): void {
    User::factory()->customer()->count(2)->create();
    $admin = User::factory()->admin()->create();
    $ops = User::factory()->role(Role::Ops)->create();

    $this->withToken($admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken)
        ->getJson('/api/admin/customers')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $opsToken = $ops->createToken('web', ['*'], now()->addDays(30))->plainTextToken;
    $this->withToken($opsToken)->getJson('/api/admin/customers')->assertStatus(403);

    $customer = User::factory()->customer()->create();
    $this->withToken($opsToken)->postJson("/api/admin/customers/{$customer->id}/block", [
        'reason' => 'nope',
    ])->assertStatus(403);
});

it('blocks and unblocks customers with audit old and new values', function (): void {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create(['password' => Hash::make('secret-pass-1')]);
    $token = $customer->createToken('web', ['*'], now()->addDays(30))->plainTextToken;
    $adminToken = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($adminToken)->postJson("/api/admin/customers/{$customer->id}/block", [
        'reason' => 'QA test',
    ])->assertOk()->assertJsonPath('user.status', 'blocked');

    expect(AuditLog::query()->where('action', 'customer.blocked')->count())->toBe(1)
        ->and(AuditLog::query()->first()->old_values)->toBe(['status' => 'active'])
        ->and(AuditLog::query()->first()->new_values)->toBe(['status' => 'blocked']);

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'account_blocked');

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');

    $this->postJson('/api/auth/login', [
        'email' => $customer->email,
        'password' => 'secret-pass-1',
    ])->assertStatus(403)->assertJsonPath('code', 'account_blocked');

    $this->withToken($adminToken)->postJson("/api/admin/customers/{$customer->id}/block", [
        'reason' => 'again',
    ])->assertOk();

    expect(AuditLog::query()->where('action', 'customer.blocked')->count())->toBe(1);

    $this->withToken($adminToken)->postJson("/api/admin/customers/{$customer->id}/unblock", [
        'reason' => 'cleared',
    ])->assertOk()->assertJsonPath('user.status', 'active');

    expect(AuditLog::query()->where('action', 'customer.unblocked')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'customer.unblocked')->first()->old_values)->toBe(['status' => 'blocked'])
        ->and(AuditLog::query()->where('action', 'customer.unblocked')->first()->new_values)->toBe(['status' => 'active']);

    $this->withToken($adminToken)->postJson("/api/admin/customers/{$customer->id}/unblock", [
        'reason' => 'again',
    ])->assertOk();

    expect(AuditLog::query()->where('action', 'customer.unblocked')->count())->toBe(1);
});

it('does not seed fixtures outside local and testing', function (): void {
    $this->app['env'] = 'production';

    (new RoleFixturesSeeder)->run();

    expect(User::query()->where('email', 'admin@example.test')->exists())->toBeFalse();
});

it('ignores mass assignment of role and status on patch me', function (): void {
    $user = User::factory()->customer()->create();
    $token = $user->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)->patchJson('/api/auth/me', [
        'name' => 'New Name',
        'role' => 'admin',
        'status' => 'blocked',
    ])->assertOk()
        ->assertJsonPath('user.name', 'New Name')
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonPath('user.status', 'active');
});
