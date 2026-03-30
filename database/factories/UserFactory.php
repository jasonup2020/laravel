<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        $salt = Str::random(6);
        
        return [
            'tenant_id' => 1,
            'username' => $this->faker->unique()->userName,
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->optional()->phoneNumber,
            'avatar' => $this->faker->optional()->imageUrl(200, 200),
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'remember_token' => Str::random(10),
            'status' => 1,
            'is_admin' => 0,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => 1,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
