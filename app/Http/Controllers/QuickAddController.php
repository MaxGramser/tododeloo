<?php

namespace App\Http\Controllers;

use App\Actions\Todos\QuickAddTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuickAddController extends Controller
{
    public function __invoke(Request $request, QuickAddTodo $quickAdd): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'list_id' => ['nullable', 'integer'],
        ]);

        $contextList = isset($validated['list_id'])
            ? $request->user()->lists()->find($validated['list_id'])
            : null;

        $quickAdd($request->user(), $validated['title'], $contextList);

        return back();
    }
}
