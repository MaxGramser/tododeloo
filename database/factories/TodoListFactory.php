<?php

namespace Database\Factories;

use App\Enums\ListType;
use App\Enums\SortMode;
use App\Models\TodoList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TodoList>
 */
class TodoListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ListType::Custom,
            'name' => fake()->words(2, true),
            'date' => null,
            'sort_mode' => SortMode::CreatedAt,
        ];
    }

    public function master(): static
    {
        return $this->state(fn () => [
            'type' => ListType::Master,
            'name' => 'Master',
            'date' => null,
        ]);
    }

    public function daily(?\DateTimeInterface $date = null): static
    {
        return $this->state(fn () => [
            'type' => ListType::Daily,
            'name' => null,
            'date' => $date ?? today(),
        ]);
    }
}
