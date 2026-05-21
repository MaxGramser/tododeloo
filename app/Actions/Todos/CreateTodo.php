<?php

namespace App\Actions\Todos;

use App\Actions\Lists\EnsureMasterList;
use App\Enums\Priority;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTodo
{
    public function __construct(
        private readonly EnsureMasterList $ensureMasterList,
        private readonly AddTodoToList $addTodoToList,
    ) {}

    /**
     * Create a todo, attach to master, optionally also attach to an additional list.
     *
     * @param  array{title: string, description?: ?string, priority?: Priority}  $data
     */
    public function __invoke(User $user, array $data, ?TodoList $additionalList = null): Todo
    {
        return DB::transaction(function () use ($user, $data, $additionalList) {
            $todo = Todo::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? Priority::Normal,
            ]);

            $master = ($this->ensureMasterList)($user);
            ($this->addTodoToList)($todo, $master);

            if ($additionalList !== null && $additionalList->id !== $master->id) {
                ($this->addTodoToList)($todo, $additionalList);
            }

            return $todo;
        });
    }
}
