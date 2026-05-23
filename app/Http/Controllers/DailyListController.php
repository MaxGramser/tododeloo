<?php

namespace App\Http\Controllers;

use App\Actions\Recurrences\MaterializeRecurrences;
use App\Actions\Todos\BuildRitualCandidates;
use App\Actions\Todos\ResetRitual;
use App\Actions\Todos\StartDay;
use App\Enums\ListType;
use App\Http\Resources\TodoListResource;
use App\Http\Resources\TodoResource;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyListController extends Controller
{
    public function __construct(
        private readonly MaterializeRecurrences $materializeRecurrences,
        private readonly BuildRitualCandidates $buildRitualCandidates,
    ) {}

    public function today(Request $request): Response
    {
        return $this->renderForDate($request, CarbonImmutable::today());
    }

    public function show(Request $request, string $date): Response
    {
        return $this->renderForDate($request, CarbonImmutable::parse($date));
    }

    public function start(Request $request, StartDay $startDay, string $date): RedirectResponse
    {
        $validated = $request->validate([
            'carry_over_ids' => 'array',
            'carry_over_ids.*' => 'integer',
            'new_titles' => 'array',
            'new_titles.*' => 'string|max:255',
        ]);

        $startDay(
            $request->user(),
            CarbonImmutable::parse($date),
            $validated['carry_over_ids'] ?? [],
            $validated['new_titles'] ?? [],
        );

        return back();
    }

    /**
     * Re-open the morning ritual for a date so it can be run again.
     */
    public function reset(Request $request, ResetRitual $resetRitual, string $date): RedirectResponse
    {
        $resetRitual($request->user(), CarbonImmutable::parse($date));

        return back();
    }

    private function renderForDate(Request $request, CarbonImmutable $date): Response
    {
        $user = $request->user();
        $isToday = $date->isSameDay(CarbonImmutable::today());

        // Bring recurring todos onto today before we read the list, so they
        // surface as pre-scheduled ("al gepland") in the ritual.
        if ($isToday) {
            ($this->materializeRecurrences)($user, $date);
        }

        $list = $user->lists()
            ->where('type', ListType::Daily)
            ->whereDate('date', $date)
            ->with(['todos.tags', 'todos.lists', 'todos.subTodos', 'todos.recurrence'])
            ->first();

        $needsRitual = $isToday && ($list === null || $list->started_at === null);

        $ritual = $needsRitual
            ? ($this->buildRitualCandidates)($user, $date, $list)
            : null;

        $previousWorkday = $ritual['previousWorkday'] ?? Workday::lastWorkdayBefore($date);

        $resolvedList = (! $needsRitual && $list)
            ? TodoListResource::make($list)->resolve()
            : null;

        return Inertia::render('lists/Day', [
            'date' => $date->toDateString(),
            'isToday' => $isToday,
            'list' => $resolvedList,
            'needsRitual' => $needsRitual,
            'previousWorkday' => $previousWorkday->toDateString(),
            'carryOverCandidates' => TodoResource::collection($ritual['carryOverCandidates'] ?? collect())->resolve(),
            'earlierCandidates' => TodoResource::collection($ritual['earlierCandidates'] ?? collect())->resolve(),
            'masterOpenTodos' => TodoResource::collection($ritual['masterOpenTodos'] ?? collect())->resolve(),
            'preScheduled' => TodoResource::collection($ritual['preScheduled'] ?? collect())->resolve(),
        ]);
    }
}
