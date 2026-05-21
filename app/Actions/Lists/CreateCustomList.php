<?php

namespace App\Actions\Lists;

use App\Enums\ListType;
use App\Enums\SortMode;
use App\Models\TodoList;
use App\Models\User;

class CreateCustomList
{
    public function __invoke(User $user, string $name): TodoList
    {
        return TodoList::create([
            'user_id' => $user->id,
            'type' => ListType::Custom,
            'name' => $name,
            'sort_mode' => SortMode::Manual,
        ]);
    }
}
