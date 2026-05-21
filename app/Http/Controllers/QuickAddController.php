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
        ]);

        $quickAdd($request->user(), $validated['title']);

        return back();
    }
}
