<?php

namespace App\Http\Controllers;

use App\Actions\Tags\CreateTag;
use App\Actions\Tags\DeleteTag;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request, CreateTag $createTag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:40',
            'color' => 'nullable|string|max:20',
        ]);

        $createTag($request->user(), $validated['name'], $validated['color'] ?? null);

        return back();
    }

    public function destroy(Request $request, DeleteTag $delete, Tag $tag): RedirectResponse
    {
        abort_if($tag->user_id !== $request->user()->id, 403);
        $delete($tag);

        return back();
    }
}
