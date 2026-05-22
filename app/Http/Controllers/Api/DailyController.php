<?php

namespace App\Http\Controllers\Api;

use App\Actions\Recurrences\MaterializeRecurrences;
use App\Actions\Todos\StartDay;
use App\Enums\ListType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoListResource;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use App\Models\User;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class DailyController extends Controller
{
    public function __construct(
        private readonly MaterializeRecurrences $materializeRecurrences,
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

        $previousWorkday = Workday::lastWorkdayBefore($date);
        $carryOverCandidates = collect();
        $earlierCandidates = collect();
        $masterOpenTodos = collect();
        $preScheduled = collect();

        if ($needsRitual) {
            $previousList = $user->lists()
                ->where('type', ListType::Daily)
                ->whereDate('date', $previousWorkday)
                ->first();

            if ($previousList) {
                $carryOverCandidates = $previousList->todos()->active()->notRecurring()->with('tags')->get();
            }

            $earlierCandidates = $this->earlierCandidates($user, $previousWorkday);

            if ($list) {
                $preScheduled = $list->todos()->active()->with('tags')->get();
            }

            $masterOpenTodos = $user->todos()->active()->with('tags')->latest()->limit(50)->get();
        }

        $resolvedList = (! $needsRitual && $list)
            ? TodoListResource::make($list)->resolve()
            : null;

        return [
            'date' => $date->toDateString(),
            'is_today' => $isToday,
            'list' => $resolvedList,
            'needs_ritual' => $needsRitual,
            'previous_workday' => $previousWorkday->toDateString(),
            'carry_over_candidates' => TodoResource::collection($carryOverCandidates)->resolve(),
            'earlier_candidates' => TodoResource::collection($earlierCandidates)->resolve(),
            'master_open_todos' => TodoResource::collection($masterOpenTodos)->resolve(),
            'pre_scheduled' => TodoResource::collection($preScheduled)->resolve(),
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
}
