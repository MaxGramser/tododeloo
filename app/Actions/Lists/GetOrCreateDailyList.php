<?php

namespace App\Actions\Lists;

use App\Enums\ListType;
use App\Enums\SortMode;
use App\Models\TodoList;
use App\Models\User;
use DateTimeInterface;

class GetOrCreateDailyList
{
    public function __invoke(User $user, DateTimeInterface $date): TodoList
    {
        return TodoList::firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => ListType::Daily,
                'date' => $date,
            ],
            ['sort_mode' => SortMode::Manual],
        );
    }
}
