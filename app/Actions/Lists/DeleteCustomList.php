<?php

namespace App\Actions\Lists;

use App\Models\TodoList;

class DeleteCustomList
{
    /**
     * Soft-delete a custom list. Todos remain on master; only the list-references die with it.
     */
    public function __invoke(TodoList $list): void
    {
        $list->delete();
    }
}
