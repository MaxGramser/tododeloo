<?php

namespace App\Http\Controllers;

use App\Actions\Lists\UpdateListSortMode;
use App\Enums\SortMode;
use App\Models\TodoList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListSortModeController extends Controller
{
    public function __invoke(Request $request, UpdateListSortMode $update, TodoList $list): RedirectResponse
    {
        abort_if($list->user_id !== $request->user()->id, 403);
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

        return back();
    }
}
