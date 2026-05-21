<?php

namespace App\Actions\SubTodos;

use App\Models\SubTodo;

class UpdateSubTodo
{
    /**
     * @param  array{title?: string}  $data
     */
    public function __invoke(SubTodo $sub, array $data): SubTodo
    {
        $sub->update($data);

        return $sub->fresh();
    }
}
