<?php

namespace App\Http\Controllers\Api;

use App\Actions\Recurrences\MaterializeRecurrences;
use App\Actions\Todos\BuildRitualCandidates;
use App\Actions\Todos\BuildUpcomingSchedule;
use App\Actions\Todos\StartDay;
use App\Enums\ListType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoListResource;
use App\Http\Resources\TodoResource;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DailyController extends Controller
{
    public function __construct(
        private readonly MaterializeRecurrences $materializeRecurrences,
        private readonly BuildRitualCandidates $buildRitualCandidates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function today(Request $request): array
    {
        return $this->payloadForDate($request, CarbonImmutable::today());
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $date): array
    {
        return $this->payloadForDate($request, CarbonImmutable::parse($date));
    }

    /**
     * The days ahead that already hold scheduled todos, oldest first, so the
     * client can show when each todo becomes relevant.
     *
     * @return array<string, mixed>
     */
    public function upcoming(Request $request, BuildUpcomingSchedule $buildUpcomingSchedule): array
    {
        $days = $buildUpcomingSchedule($request->user());

        return [
            'days' => TodoListResource::collection($days)->resolve(),
        ];
    }

    /**
     * Run the morning ritual for a date and return the started list.
     *
     * @return array<string, mixed>
     */
    public function start(Request $request, StartDay $startDay, string $date): array
    {
        $validated = $request->validate([
            'carry_over_ids' => 'array',
            'carry_over_ids.*' => 'integer',
            'new_titles' => 'array',
            'new_titles.*' => 'string|max:255',
        ]);

        $list = $startDay(
            $request->user(),
            CarbonImmutable::parse($date),
            $validated['carry_over_ids'] ?? [],
            $validated['new_titles'] ?? [],
        );

        return [
            'list' => TodoListResource::make($list->load(['todos.tags', 'todos.lists', 'todos.subTodos', 'todos.recurrence']))->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForDate(Request $request, CarbonImmutable $date): array
    {
        $user = $request->user();
        $isToday = $date->isSameDay(CarbonImmutable::today());

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

        return [
            'date' => $date->toDateString(),
            'is_today' => $isToday,
            'list' => $resolvedList,
            'needs_ritual' => $needsRitual,
            'previous_workday' => $previousWorkday->toDateString(),
            'carry_over_candidates' => TodoResource::collection($ritual['carryOverCandidates'] ?? collect())->resolve(),
            'earlier_candidates' => TodoResource::collection($ritual['earlierCandidates'] ?? collect())->resolve(),
            'master_open_todos' => TodoResource::collection($ritual['masterOpenTodos'] ?? collect())->resolve(),
            'pre_scheduled' => TodoResource::collection($ritual['preScheduled'] ?? collect())->resolve(),
        ];
    }
}
