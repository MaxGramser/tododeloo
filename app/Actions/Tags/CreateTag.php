<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Models\User;

class CreateTag
{
    public function __invoke(User $user, string $name, ?string $color = null): Tag
    {
        return Tag::firstOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            ['color' => $color],
        );
    }
}
