<?php

namespace App\Http\Controllers;

use App\Actions\Tags\SyncTodoTags;
use App\Actions\Todos\AddTodoToList;
use App\Actions\Todos\AddTodoToToday;
use App\Actions\Todos\CompleteTodo;
use App\Actions\Todos\CreateTodo;
use App\Actions\Todos\DeleteTodo;
use App\Actions\Todos\DuplicateTodo;
use App\Actions\Todos\MoveTodoToDate;
use App\Actions\Todos\RemoveTodoFromList;
use App\Actions\Todos\RestoreTodo;
use App\Actions\Todos\UncompleteTodo;
use App\Actions\Todos\UpdateTodo;
use App\Enums\Priority;
use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TodoController extends Controller
{
    public function store(Request $request, CreateTodo $createTodo): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'list_id' => 'nullable|integer|exists:todo_lists,id',
        ]);

        $additional = null;
        if (! empty($validated['list_id'])) {
            $additional = TodoList::find($validated['list_id']);
            abort_if($additional?->user_id !== $request->user()->id, 403);
        }

        $createTodo(
            $request->user(),
            [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => isset($validated['priority']) ? Priority::from($validated['priority']) : Priority::Normal,
            ],
            $additional,
        );

        return back();
    }

    public function update(Request $request, UpdateTodo $update, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'priority' => ['sometimes', Rule::enum(Priority::class)],
        ]);

        if (isset($validated['priority'])) {
            $validated['priority'] = Priority::from($validated['priority']);
        }

        $update($todo, $validated);

        return back();
    }

    public function destroy(Request $request, DeleteTodo $delete, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $delete($todo);

        return back();
    }

    public function restore(Request $request, RestoreTodo $restore, int $todo): RedirectResponse
    {
        $model = Todo::withTrashed()->findOrFail($todo);
        $this->ensureOwns($request, $model);
        $restore($model);

        return back();
    }

    public function complete(Request $request, CompleteTodo $complete, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $complete($todo);

        return back();
    }

    public function uncomplete(Request $request, UncompleteTodo $uncomplete, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $uncomplete($todo);

        return back();
    }

    public function addToList(Request $request, AddTodoToList $addToList, Todo $todo, TodoList $list): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        abort_if($list->user_id !== $request->user()->id, 403);

        $addToList($todo, $list);

        return back();
    }

    public function addToToday(Request $request, AddTodoToToday $addToToday, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $addToToday($todo);

        return back();
    }

    public function duplicate(Request $request, DuplicateTodo $duplicate, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $validated = $request->validate([
            'list_id' => 'nullable|integer|exists:todo_lists,id',
        ]);

        $additional = null;
        if (! empty($validated['list_id'])) {
            $additional = TodoList::find($validated['list_id']);
            abort_if($additional?->user_id !== $request->user()->id, 403);
        }

        $duplicate($todo, $additional);

        return back();
    }

    public function moveToDate(Request $request, MoveTodoToDate $move, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'from_list_id' => 'nullable|integer|exists:todo_lists,id',
        ]);

        $fromList = null;
        if (! empty($validated['from_list_id'])) {
            $fromList = TodoList::find($validated['from_list_id']);
            abort_if($fromList?->user_id !== $request->user()->id, 403);
        }

        $move($todo, \Carbon\CarbonImmutable::parse($validated['date']), $fromList);

        return back();
    }

    public function removeFromList(Request $request, RemoveTodoFromList $remove, Todo $todo, TodoList $list): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        abort_if($list->user_id !== $request->user()->id, 403);

        $remove($todo, $list);

        return back();
    }

    public function syncTags(Request $request, SyncTodoTags $syncTags, Todo $todo): RedirectResponse
    {
        $this->ensureOwns($request, $todo);
        $validated = $request->validate([
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ]);

        $syncTags($todo, $validated['tag_ids'] ?? []);

        return back();
    }

    private function ensureOwns(Request $request, Todo $todo): void
    {
        abort_if($todo->user_id !== $request->user()->id, 403);
    }
}
