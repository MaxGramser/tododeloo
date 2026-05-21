<?php

namespace Database\Factories;

use App\Models\SubTodo;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubTodo>
 */
class SubTodoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'todo_id' => Todo::factory(),
            'title' => fake()->sentence(3),
            'completed_at' => null,
            'position' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
