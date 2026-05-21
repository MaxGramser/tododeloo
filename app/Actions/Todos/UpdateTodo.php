<?php

namespace App\Actions\Todos;

use App\Enums\Priority;
use App\Models\Todo;

class UpdateTodo
{
    /**
     * @param  array{title?: string, description?: ?string, priority?: Priority}  $data
     */
    public function __invoke(Todo $todo, array $data): Todo
    {
        $todo->update($data);

        return $todo->fresh();
    }
}
