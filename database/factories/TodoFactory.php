<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => null,
            'priority' => Priority::Normal,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }

    public function priority(Priority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }
}
