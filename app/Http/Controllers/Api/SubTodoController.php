<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubTodos\CreateSubTodo;
use App\Actions\SubTodos\DeleteSubTodo;
use App\Actions\SubTodos\ToggleSubTodoDone;
use App\Actions\SubTodos\UpdateSubTodo;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoResource;
use App\Models\SubTodo;
use App\Models\Todo;
use Illuminate\Http\Request;

class SubTodoController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function store(Request $request, CreateSubTodo $createSubTodo, Todo $todo): array
    {
        abort_if($todo->user_id !== $request->user()->id, 403);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $createSubTodo($todo, $validated['title']);

        return $this->respondWithParent($todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, UpdateSubTodo $update, SubTodo $subTodo): array
    {
        $this->ensureOwns($request, $subTodo);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $update($subTodo, $validated);

        return $this->respondWithParent($subTodo->todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(Request $request, ToggleSubTodoDone $toggle, SubTodo $subTodo): array
    {
        $this->ensureOwns($request, $subTodo);
        $toggle($subTodo);

        return $this->respondWithParent($subTodo->todo);
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, DeleteSubTodo $delete, SubTodo $subTodo): array
    {
        $this->ensureOwns($request, $subTodo);
        $parent = $subTodo->todo;
        $delete($subTodo);

        return $this->respondWithParent($parent);
    }

    /**
     * @return array<string, mixed>
     */
    private function respondWithParent(Todo $todo): array
    {
        return [
            'todo' => TodoResource::make($todo->fresh()->load(['tags', 'lists', 'subTodos', 'recurrence']))->resolve(),
        ];
    }

    private function ensureOwns(Request $request, SubTodo $subTodo): void
    {
        abort_if($subTodo->todo->user_id !== $request->user()->id, 403);
    }
}
