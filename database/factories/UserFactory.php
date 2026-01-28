<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\User\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Domain\User\Models\User::class;
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'FullName' => fake()->name(),
            'Email' => fake()->unique()->safeEmail(),
            'IsVerified' => true,
            'PasswordHash' => static::$password ??= Hash::make('password'),
            'Phone' => fake()->phoneNumber(),
            'Gender' => fake()->randomElement(['Male', 'Female']),
            'DateOfBirth' => fake()->date(),
            'CreatedAt' => now(),
            'AuthProvider' => 'email',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'IsVerified' => false,
        ]);
    }
}
