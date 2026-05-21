<?php

namespace App\Actions\SubTodos;

use App\Models\SubTodo;
use Illuminate\Support\Facades\DB;

class ToggleSubTodoDone
{
    /**
     * Toggle this subtodo's done-state. After toggling, sync the parent:
     * - if all subs are done → mark parent done
     * - if any sub is open → mark parent open (re-evaluate even when re-opening)
     */
    public function __invoke(SubTodo $sub): SubTodo
    {
        return DB::transaction(function () use ($sub) {
            $sub->update([
                'completed_at' => $sub->isCompleted() ? null : now(),
            ]);
            $sub->refresh();

            $parent = $sub->todo->fresh();
            $hasOpen = $parent->subTodos()->whereNull('completed_at')->exists();

            if (! $hasOpen && ! $parent->isCompleted()) {
                $parent->update(['completed_at' => now()]);
            } elseif ($hasOpen && $parent->isCompleted()) {
                $parent->update(['completed_at' => null]);
            }

            return $sub;
        });
    }
}
