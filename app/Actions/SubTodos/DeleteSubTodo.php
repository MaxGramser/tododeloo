<?php

namespace App\Actions\SubTodos;

use App\Models\SubTodo;
use Illuminate\Support\Facades\DB;

class DeleteSubTodo
{
    /**
     * Deletes a subtodo. After deletion, if the parent now has all subs done
     * (or no subs at all and was previously open because of subs), we re-evaluate.
     * The simplest invariant: parent is done iff it has subs AND all subs are done.
     * If after delete there are no subs, parent's done-state is left as-is.
     */
    public function __invoke(SubTodo $sub): void
    {
        DB::transaction(function () use ($sub) {
            $parent = $sub->todo;
            $sub->delete();

            $remaining = $parent->subTodos()->count();
            if ($remaining === 0) {
                return;
            }

            $hasOpen = $parent->subTodos()->whereNull('completed_at')->exists();
            if (! $hasOpen && ! $parent->isCompleted()) {
                $parent->update(['completed_at' => now()]);
            }
        });
    }
}
