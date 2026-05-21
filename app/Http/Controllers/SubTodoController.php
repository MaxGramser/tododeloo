<?php

namespace App\Http\Controllers;

use App\Actions\SubTodos\CreateSubTodo;
use App\Actions\SubTodos\DeleteSubTodo;
use App\Actions\SubTodos\ToggleSubTodoDone;
use App\Actions\SubTodos\UpdateSubTodo;
use App\Models\SubTodo;
use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubTodoController extends Controller
{
    public function store(Request $request, CreateSubTodo $createSubTodo, Todo $todo): RedirectResponse
    {
        abort_if($todo->user_id !== $request->user()->id, 403);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $createSubTodo($todo, $validated['title']);

        return back();
    }

    public function update(Request $request, UpdateSubTodo $update, SubTodo $subTodo): RedirectResponse
    {
        $this->ensureOwns($request, $subTodo);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $update($subTodo, $validated);

        return back();
    }

    public function toggle(Request $request, ToggleSubTodoDone $toggle, SubTodo $subTodo): RedirectResponse
    {
        $this->ensureOwns($request, $subTodo);
        $toggle($subTodo);

        return back();
    }

    public function destroy(Request $request, DeleteSubTodo $delete, SubTodo $subTodo): RedirectResponse
    {
        $this->ensureOwns($request, $subTodo);
        $delete($subTodo);

        return back();
    }

    private function ensureOwns(Request $request, SubTodo $subTodo): void
    {
        abort_if($subTodo->todo->user_id !== $request->user()->id, 403);
    }
}
