<?php

namespace App\Observers;

use App\Actions\Lists\EnsureMasterList;
use App\Models\User;

class UserObserver
{
    public function __construct(
        private readonly EnsureMasterList $ensureMasterList,
    ) {}

    public function created(User $user): void
    {
        ($this->ensureMasterList)($user);
    }
}
