<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'my',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->role === null) {
                $user->forceFill(['role' => Role::Customer])->save();
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['role' => Role::Admin])->save();
        });
    }

    public function customer(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['role' => Role::Customer])->save();
        });
    }

    public function blocked(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill([
                'status' => UserStatus::Blocked,
                'blocked_at' => now(),
            ])->save();
        });
    }

    public function role(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->forceFill(['role' => $role])->save();
        });
    }
}
