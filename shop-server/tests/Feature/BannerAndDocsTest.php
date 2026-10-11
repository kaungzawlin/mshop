<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\SettingsSeeder;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

it('allows admin to manage scheduled banners', function (): void {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $create = $this->withToken($token)
        ->postJson('/api/admin/banners', [
            'title' => 'Thingyan Sale',
            'subtitle' => 'Up to 30% off',
            'image_url' => 'https://example.test/banner.jpg',
            'is_active' => true,
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDays(3)->toIso8601String(),
        ])
        ->assertCreated();

    expect($create->json('data.title'))->toBe('Thingyan Sale');

    $this->getJson('/api/catalog/banners')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Thingyan Sale');
});

it('forbids openapi docs for non-admin', function (): void {
    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/openapi.json')
        ->assertForbidden();
});

it('returns openapi for admin', function (): void {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.0.3')
        ->assertJsonPath('info.title', 'mshop API');
});

it('allows responsible and system ping', function (): void {
    foreach (['responsible', 'system'] as $role) {
        $user = User::factory()->role(Role::from($role))->create();
        $token = $user->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/{$role}/ping")
            ->assertOk()
            ->assertJsonPath('role', $role);
    }
});
