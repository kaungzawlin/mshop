<?php

use App\Models\User;
use App\Services\Settings\RuntimeConfigResolver;
use Database\Seeders\SettingsSeeder;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

it('lists settings groups for admin', function (): void {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => [['group', 'label', 'enabled', 'values']]]);
});

it('updates a settings group and masks secrets', function (): void {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/admin/settings/mail', [
            'enabled' => true,
            'driver' => 'log',
            'from_address' => 'hello@mshop.test',
            'password' => 'super-secret',
        ])
        ->assertOk()
        ->assertJsonPath('values.from_address', 'hello@mshop.test')
        ->assertJsonPath('values.password', '••••••••');

    $resolver = app(RuntimeConfigResolver::class);
    $resolver->forgetCache();

    expect($resolver->get('mail', 'from_address'))->toBe('hello@mshop.test')
        ->and($resolver->get('mail', 'password'))->toBe('super-secret');
});

it('forbids settings for non-admin', function (): void {
    $user = User::factory()->customer()->create();
    $token = $user->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/settings')
        ->assertForbidden();
});
