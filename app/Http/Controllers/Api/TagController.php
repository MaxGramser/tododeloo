<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tags\CreateTag;
use App\Actions\Tags\DeleteTag;
use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return [
            'tags' => TagResource::collection(
                $request->user()->tags()->orderBy('name')->get(),
            )->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request, CreateTag $createTag): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:40',
            'color' => 'nullable|string|max:20',
        ]);

        $tag = $createTag($request->user(), $validated['name'], $validated['color'] ?? null);

        return ['tag' => TagResource::make($tag)->resolve()];
    }

    public function destroy(Request $request, DeleteTag $delete, Tag $tag): JsonResponse
    {
        abort_if($tag->user_id !== $request->user()->id, 403);
        $delete($tag);

        return response()->json(['message' => 'Tag deleted.']);
    }
}
