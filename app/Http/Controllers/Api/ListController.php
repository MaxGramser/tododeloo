<?php

namespace App\Http\Controllers\Api;

use App\Actions\Lists\CreateCustomList;
use App\Actions\Lists\DeleteCustomList;
use App\Actions\Lists\EnsureMasterList;
use App\Actions\Lists\ReorderListItems;
use App\Actions\Lists\UpdateCustomList;
use App\Actions\Lists\UpdateListSortMode;
use App\Enums\ListType;
use App\Enums\SortMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoListResource;
use App\Models\TodoList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListController extends Controller
{
    /**
     * Lightweight overview of the user's master + custom lists with todo counts.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $lists = $request->user()->lists()
            ->whereIn('type', [ListType::Master, ListType::Custom])
            ->withCount([
                'todos as open_count' => fn ($query) => $query->whereNull('completed_at'),
                'todos as total_count',
            ])
            ->orderByRaw("CASE WHEN type = 'master' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return [
            'lists' => $lists->map(fn (TodoList $list) => [
                'id' => $list->id,
                'type' => $list->type->value,
                'name' => $list->name,
                'date' => $list->date?->toDateString(),
                'sort_mode' => $list->sort_mode->value,
                'open_count' => (int) $list->open_count,
                'total_count' => (int) $list->total_count,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function master(Request $request, EnsureMasterList $ensureMasterList): array
    {
        $list = $ensureMasterList($request->user());

        return ['list' => $this->resolveWithTodos($list)];
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request, CreateCustomList $createCustomList): array
    {
        $validated = $request->validate(['name' => 'required|string|max:80']);
        $list = $createCustomList($request->user(), $validated['name']);

        return ['list' => $this->resolveWithTodos($list)];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, TodoList $list): array
    {
        $this->authorizeAccess($request, $list);

        return ['list' => $this->resolveWithTodos($list)];
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, UpdateCustomList $update, TodoList $list): array
    {
        $this->authorizeAccess($request, $list);
        abort_unless($list->type === ListType::Custom, 404);
        $validated = $request->validate(['name' => 'required|string|max:80']);

        $update($list, $validated['name']);

        return ['list' => $this->resolveWithTodos($list->fresh())];
    }

    public function destroy(Request $request, DeleteCustomList $delete, TodoList $list): JsonResponse
    {
        $this->authorizeAccess($request, $list);
        abort_unless($list->type === ListType::Custom, 404);

        $delete($list);

        return response()->json(['message' => 'List deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    public function reorder(Request $request, ReorderListItems $reorder, TodoList $list): array
    {
        $this->authorizeAccess($request, $list);
        $validated = $request->validate([
            'todo_ids' => 'required|array',
            'todo_ids.*' => 'integer',
        ]);

        $reorder($list, $validated['todo_ids']);

        return ['list' => $this->resolveWithTodos($list->fresh())];
    }

    /**
     * @return array<string, mixed>
     */
    public function sortMode(Request $request, UpdateListSortMode $update, TodoList $list): array
    {
        $this->authorizeAccess($request, $list);
        $validated = $request->validate([
            'sort_mode' => ['required', Rule::enum(SortMode::class)],
            'visible_todo_ids' => 'nullable|array',
            'visible_todo_ids.*' => 'integer',
        ]);

        $update(
            $list,
            SortMode::from($validated['sort_mode']),
            $validated['visible_todo_ids'] ?? null,
        );

        return ['list' => $this->resolveWithTodos($list->fresh())];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWithTodos(TodoList $list): array
    {
        return TodoListResource::make(
            $list->load(['todos.tags', 'todos.lists', 'todos.subTodos']),
        )->resolve();
    }

    private function authorizeAccess(Request $request, TodoList $list): void
    {
        abort_if($list->user_id !== $request->user()->id, 403);
    }
}
