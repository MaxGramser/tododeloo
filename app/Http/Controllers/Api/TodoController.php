<?php

namespace App\Http\Controllers\Api;

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
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TodoController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function store(Request $request, CreateTodo $createTodo): array
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

        $todo = $createTodo(
            $request->user(),
            [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => isset($validated['priority']) ? Priority::from($validated['priority']) : Priority::Normal,
            ],
            $additional,
        );

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, UpdateTodo $update, Todo $todo): array
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

        return $this->respondWith($todo);
    }

    public function destroy(Request $request, DeleteTodo $delete, Todo $todo): JsonResponse
    {
        $this->ensureOwns($request, $todo);
        $delete($todo);

        return response()->json(['message' => 'Todo deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(Request $request, RestoreTodo $restore, int $todo): array
    {
        $model = Todo::withTrashed()->findOrFail($todo);
        $this->ensureOwns($request, $model);
        $restore($model);

        return $this->respondWith($model);
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(Request $request, CompleteTodo $complete, Todo $todo): array
    {
        $this->ensureOwns($request, $todo);
        $complete($todo);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function uncomplete(Request $request, UncompleteTodo $uncomplete, Todo $todo): array
    {
        $this->ensureOwns($request, $todo);
        $uncomplete($todo);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function addToList(Request $request, AddTodoToList $addToList, Todo $todo, TodoList $list): array
    {
        $this->ensureOwns($request, $todo);
        abort_if($list->user_id !== $request->user()->id, 403);

        $addToList($todo, $list);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function addToToday(Request $request, AddTodoToToday $addToToday, Todo $todo): array
    {
        $this->ensureOwns($request, $todo);
        $addToToday($todo);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicate(Request $request, DuplicateTodo $duplicate, Todo $todo): array
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

        $copy = $duplicate($todo, $additional);

        return $this->respondWith($copy);
    }

    /**
     * @return array<string, mixed>
     */
    public function moveToDate(Request $request, MoveTodoToDate $move, Todo $todo): array
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

        $move($todo, CarbonImmutable::parse($validated['date']), $fromList);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeFromList(Request $request, RemoveTodoFromList $remove, Todo $todo, TodoList $list): array
    {
        $this->ensureOwns($request, $todo);
        abort_if($list->user_id !== $request->user()->id, 403);

        $remove($todo, $list);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncTags(Request $request, SyncTodoTags $syncTags, Todo $todo): array
    {
        $this->ensureOwns($request, $todo);
        $validated = $request->validate([
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ]);

        $syncTags($todo, $validated['tag_ids'] ?? []);

        return $this->respondWith($todo);
    }

    /**
     * @return array<string, mixed>
     */
    private function respondWith(Todo $todo): array
    {
        return [
            'todo' => TodoResource::make($todo->load(['tags', 'lists', 'subTodos']))->resolve(),
        ];
    }

    private function ensureOwns(Request $request, Todo $todo): void
    {
        abort_if($todo->user_id !== $request->user()->id, 403);
    }
}
