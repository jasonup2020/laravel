<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = \App\Models\Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'domain' => $this->faker->unique()->domainName,
            'logo' => $this->faker->optional()->imageUrl(200, 200),
            'contact_name' => $this->faker->name,
            'contact_phone' => $this->faker->phoneNumber,
            'contact_email' => $this->faker->email,
            'address' => $this->faker->address,
            'config' => null,
            'expire_at' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'status' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
