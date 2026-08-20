<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->userName().'@bscse.uiu.ac.bd',
            'password' => Hash::make('demo123'),
            'role' => 'student',
            'authority_level' => 'member',
            'status' => 'active',
            'avatar_initials' => strtoupper(Str::substr(fake()->firstName(), 0, 2)),
        ];
    }
}
