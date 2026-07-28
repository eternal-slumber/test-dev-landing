<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'comment' => fake()->paragraph(),
            'ai_category' => null,
            'ai_summary' => null,
            'ai_suggested_reply' => null,
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
