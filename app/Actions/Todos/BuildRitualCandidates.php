<?php

namespace App\Actions\Todos;

use App\Enums\ListType;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class BuildRitualCandidates
{
    /**
     * Gather the morning-ritual buckets for a date. A todo surfaces in exactly
     * one bucket, in priority order: pre-scheduled (already on today) >
     * carry-over (previous workday) > earlier (long-neglected) >
     * missed-recurring (open recurrence instances from past days) > master
     * (everything else still open). This guarantees no duplicate todos across
     * the ritual, on every client.
     *
     * @return array{
     *     previousWorkday: CarbonImmutable,
     *     carryOverCandidates: SupportCollection<int, Todo>,
     *     earlierCandidates: Collection<int, Todo>,
     *     missedRecurring: SupportCollection<int, Todo>,
     *     masterOpenTodos: Collection<int, Todo>,
     *     preScheduled: Collection<int, Todo>,
     * }
     */
    public function __invoke(User $user, CarbonImmutable $date, ?TodoList $list): array
    {
        $previousWorkday = Workday::lastWorkdayBefore($date);

        $preScheduled = $list
            ? $list->todos()->active()->with('tags')->get()
            : collect();

        $previousList = $user->lists()
            ->where('type', ListType::Daily)
            ->whereDate('date', $previousWorkday)
            ->first();

        $carryOverCandidates = $previousList
            ? $previousList->todos()->active()->notRecurring()->with('tags')->get()
            : collect();

        // Pre-scheduled wins: never repeat a todo already sitting on today.
        $carryOverCandidates = $carryOverCandidates
            ->reject(fn (Todo $todo) => $preScheduled->contains('id', $todo->id))
            ->values();

        // Earlier excludes the previous workday and later by date, so it never
        // overlaps carry-over or pre-scheduled.
        $earlierCandidates = $this->earlierCandidates($user, $previousWorkday);

        // Open recurrence instances stranded on past days — collapsed to one
        // row per recurrence with a missed_count, so the ritual stays calm.
        $missedRecurring = $this->missedRecurring($user, $date);

        $seenIds = $preScheduled->pluck('id')
            ->merge($carryOverCandidates->pluck('id'))
            ->merge($earlierCandidates->pluck('id'))
            ->all();

        // Master never shows recurrence instances: today's land in
        // pre-scheduled, past ones in missed-recurring above.
        $masterOpenTodos = $user->todos()
            ->active()
            ->notRecurring()
            ->whereNotIn('id', $seenIds)
            ->with('tags')
            ->latest()
            ->limit(50)
            ->get();

        return [
            'previousWorkday' => $previousWorkday,
            'carryOverCandidates' => $carryOverCandidates,
            'earlierCandidates' => $earlierCandidates,
            'missedRecurring' => $missedRecurring,
            'masterOpenTodos' => $masterOpenTodos,
            'preScheduled' => $preScheduled,
        ];
    }

    /**
     * Unfinished todos that sat on a daily list before $before, yet were never
     * carried onto $before or any later day — the long-neglected pile.
     *
     * @return Collection<int, Todo>
     */
    private function earlierCandidates(User $user, CarbonImmutable $before): Collection
    {
        return $user->todos()
            ->active()
            ->notRecurring()
            ->whereHas('lists', fn ($query) => $query->where('type', ListType::Daily)->whereDate('date', '<', $before))
            ->whereDoesntHave('lists', fn ($query) => $query->where('type', ListType::Daily)->whereDate('date', '>=', $before))
            ->with('tags')
            ->latest()
            ->get();
    }

    /**
     * Open recurrence instances whose occurrence date is before $date and that
     * were never carried onto today or later — the "did not happen yet" pile.
     * Collapsed to one representative per recurrence (the most recent miss),
     * carrying a transient missed_count of how many days are outstanding.
     *
     * @return SupportCollection<int, Todo>
     */
    private function missedRecurring(User $user, CarbonImmutable $date): SupportCollection
    {
        return $user->todos()
            ->active()
            ->whereNotNull('recurrence_id')
            ->whereDate('occurred_on', '<', $date)
            ->whereHas('lists', fn ($query) => $query->where('type', ListType::Daily)->whereDate('date', '<', $date))
            ->whereDoesntHave('lists', fn ($query) => $query->where('type', ListType::Daily)->whereDate('date', '>=', $date))
            ->with(['tags', 'recurrence'])
            ->get()
            ->groupBy('recurrence_id')
            ->map(function (Collection $instances): Todo {
                $representative = $instances->sortByDesc('occurred_on')->first();
                $representative->setAttribute('missed_count', $instances->count());

                return $representative;
            })
            ->values();
    }
}
