<?php

namespace App\Http\Controllers\Api;

use App\Actions\Todos\QuickAddTodo;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoResource;
use Illuminate\Http\Request;

class QuickAddController extends Controller
{
    /**
     * Quick-add a todo. Lands on today (workday) or next Monday (weekend),
     * always also on master. Returns the todo plus the date it landed on.
     *
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, QuickAddTodo $quickAdd): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $result = $quickAdd($request->user(), $validated['title']);

        return [
            'todo' => TodoResource::make($result['todo']->load(['tags', 'lists', 'subTodos']))->resolve(),
            'target_date' => $result['target_date']->toDateString(),
        ];
    }
}
