<?php

namespace App\Actions\Lists;

use App\Enums\ListType;
use App\Enums\SortMode;
use App\Models\TodoList;
use App\Models\User;

class EnsureMasterList
{
    public function __invoke(User $user): TodoList
    {
        return TodoList::firstOrCreate(
            ['user_id' => $user->id, 'type' => ListType::Master],
            ['name' => 'Master', 'sort_mode' => SortMode::CreatedAt],
        );
    }
}
