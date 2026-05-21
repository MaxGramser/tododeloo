<?php

namespace App\Http\Controllers;

use App\Actions\Lists\ReorderListItems;
use App\Models\TodoList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ListReorderController extends Controller
{
    public function __invoke(Request $request, ReorderListItems $reorder, TodoList $list): RedirectResponse
    {
        abort_if($list->user_id !== $request->user()->id, 403);
        $validated = $request->validate([
            'todo_ids' => 'required|array',
            'todo_ids.*' => 'integer',
        ]);

        $reorder($list, $validated['todo_ids']);

        return back();
    }
}
