<?php

namespace App\Actions\Todos;

use App\Models\Todo;

class RestoreTodo
{
    public function __invoke(Todo $todo): Todo
    {
        $todo->restore();

        return $todo->fresh();
    }
}
