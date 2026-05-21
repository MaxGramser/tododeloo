<?php

namespace App\Actions\Lists;

use App\Models\TodoList;

class UpdateCustomList
{
    public function __invoke(TodoList $list, string $name): TodoList
    {
        $list->update(['name' => $name]);

        return $list->fresh();
    }
}
