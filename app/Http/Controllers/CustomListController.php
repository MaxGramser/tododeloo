<?php

namespace App\Http\Controllers;

use App\Actions\Lists\CreateCustomList;
use App\Actions\Lists\DeleteCustomList;
use App\Actions\Lists\UpdateCustomList;
use App\Enums\ListType;
use App\Http\Resources\TodoListResource;
use App\Models\TodoList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomListController extends Controller
{
    public function store(Request $request, CreateCustomList $createCustomList): RedirectResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:80']);
        $list = $createCustomList($request->user(), $validated['name']);

        return redirect()->route('lists.show', $list);
    }

    public function show(Request $request, TodoList $list): Response
    {
        $this->authorizeAccess($request, $list);
        abort_unless($list->type === ListType::Custom, 404);

        return Inertia::render('lists/Custom', [
            'list' => TodoListResource::make($list->load('todos.tags'))->resolve(),
        ]);
    }

    public function update(Request $request, UpdateCustomList $update, TodoList $list): RedirectResponse
    {
        $this->authorizeAccess($request, $list);
        abort_unless($list->type === ListType::Custom, 404);
        $validated = $request->validate(['name' => 'required|string|max:80']);

        $update($list, $validated['name']);

        return back();
    }

    public function destroy(Request $request, DeleteCustomList $delete, TodoList $list): RedirectResponse
    {
        $this->authorizeAccess($request, $list);
        abort_unless($list->type === ListType::Custom, 404);

        $delete($list);

        return redirect()->route('master.show');
    }

    private function authorizeAccess(Request $request, TodoList $list): void
    {
        abort_if($list->user_id !== $request->user()->id, 403);
    }
}
