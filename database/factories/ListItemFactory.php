<?php

namespace Database\Factories;

use App\Models\ListItem;
use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListItem>
 */
class ListItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'todo_list_id' => TodoList::factory(),
            'todo_id' => Todo::factory(),
            'position' => 0,
            'added_at' => now(),
        ];
    }
}
