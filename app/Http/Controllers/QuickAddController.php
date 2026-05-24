<?php

namespace App\Http\Controllers;

use App\Actions\Todos\QuickAddTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuickAddController extends Controller
{
    public function __invoke(Request $request, QuickAddTodo $quickAdd): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'list_id' => ['nullable', 'integer'],
            'parse' => ['nullable', 'boolean'],
        ]);

        $contextList = isset($validated['list_id'])
            ? $request->user()->lists()->find($validated['list_id'])
            : null;

        $result = $quickAdd($request->user(), $validated['title'], $contextList, $validated['parse'] ?? true);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['feedback']['message'],
            'description' => $result['feedback']['description'],
        ]);

        return back();
    }
}
