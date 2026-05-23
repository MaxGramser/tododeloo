<?php

namespace App\Actions\Todos;

use App\Enums\ListType;
use App\Models\TodoList;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single source of truth for "what's coming up": the daily lists scheduled
 * after a given date that still hold a todo, ordered by date. Shared by the web
 * sidebar (lightweight) and the API upcoming endpoint (with todos eager-loaded).
 */
class BuildUpcomingSchedule
{
    /**
     * @return Collection<int, TodoList>
     */
    public function __invoke(
        User $user,
        ?CarbonImmutable $from = null,
        int $maxDays = 30,
        bool $withTodos = true,
        bool $onlyOpen = true,
    ): Collection {
        $from ??= CarbonImmutable::today();

        $query = $user->lists()
            ->where('type', ListType::Daily)
            ->whereDate('date', '>', $from)
            ->whereHas('todos', function ($todos) use ($onlyOpen) {
                if ($onlyOpen) {
                    $todos->whereNull('completed_at');
                }
            })
            ->orderBy('date')
            ->limit($maxDays);

        if ($withTodos) {
            $query->with(['todos.tags', 'todos.lists', 'todos.subTodos', 'todos.recurrence']);
        }

        return $query->get();
    }
}
