<?php

namespace App\Actions\Tags;

use App\Models\Todo;

class SyncTodoTags
{
    /**
     * @param  array<int>  $tagIds
     */
    public function __invoke(Todo $todo, array $tagIds): void
    {
        $allowedIds = $todo->user->tags()->whereIn('id', $tagIds)->pluck('id')->all();
        $todo->tags()->sync($allowedIds);
    }
}
