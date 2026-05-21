<?php

namespace App\Actions\Tags;

use App\Models\Tag;

class DeleteTag
{
    public function __invoke(Tag $tag): void
    {
        $tag->delete();
    }
}
