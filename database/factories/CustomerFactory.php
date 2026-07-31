<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'CUS-'.strtoupper($this->faker->unique()->bothify('????####')),
            'username' => $this->faker->unique()->userName(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('09########'),
            'password' => 'password',
            'status' => 'active',
        ];
    }
}
