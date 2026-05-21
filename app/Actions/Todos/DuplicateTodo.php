<?php

namespace App\Actions\Todos;

use App\Actions\Lists\EnsureMasterList;
use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Support\Facades\DB;

class DuplicateTodo
{
    public function __construct(
        private readonly EnsureMasterList $ensureMasterList,
        private readonly AddTodoToList $addTodoToList,
    ) {}

    /**
     * Create a copy of the todo (title, description, priority, tags).
     * Attaches the clone to master and, if provided, to an additional list.
     * The clone starts in the "active" state regardless of the source's completion.
     */
    public function __invoke(Todo $todo, ?TodoList $additionalList = null): Todo
    {
        return DB::transaction(function () use ($todo, $additionalList) {
            $clone = Todo::create([
                'user_id' => $todo->user_id,
                'title' => $todo->title,
                'description' => $todo->description,
                'priority' => $todo->priority,
            ]);

            $clone->tags()->sync($todo->tags()->pluck('tags.id')->all());

            $master = ($this->ensureMasterList)($todo->user);
            ($this->addTodoToList)($clone, $master);

            if ($additionalList !== null && $additionalList->id !== $master->id) {
                ($this->addTodoToList)($clone, $additionalList);
            }

            return $clone;
        });
    }
}
