<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleFixturesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $fixtures = [
            ['role' => Role::Admin, 'email' => 'admin@example.test', 'name' => 'Local Admin'],
            ['role' => Role::Ops, 'email' => 'ops@example.test', 'name' => 'Local Ops'],
            ['role' => Role::Finance, 'email' => 'finance@example.test', 'name' => 'Local Finance'],
            ['role' => Role::Responsible, 'email' => 'responsible@example.test', 'name' => 'Local Responsible'],
            ['role' => Role::System, 'email' => 'system@example.test', 'name' => 'Local System'],
            ['role' => Role::Reseller, 'email' => 'reseller@example.test', 'name' => 'Local Reseller'],
            ['role' => Role::Rider, 'email' => 'rider@example.test', 'name' => 'Local Rider'],
            ['role' => Role::Customer, 'email' => 'customer@example.test', 'name' => 'Local Customer'],
        ];

        foreach ($fixtures as $fixture) {
            $user = User::query()->firstOrNew(['email' => $fixture['email']]);
            $user->forceFill([
                'name' => $fixture['name'],
                'password' => Hash::make('password'),
                'role' => $fixture['role'],
                'status' => UserStatus::Active,
                'locale' => 'my',
                'email_verified_at' => now(),
                'blocked_at' => null,
            ])->save();
        }

        $extraCustomers = User::query()
            ->where('role', Role::Customer)
            ->where('email', '!=', 'customer@example.test')
            ->count();

        if ($extraCustomers < 10) {
            User::factory()
                ->count(10 - $extraCustomers)
                ->customer()
                ->create();
        }
    }
}
