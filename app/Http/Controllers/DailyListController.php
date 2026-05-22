<?php

namespace App\Http\Controllers;

use App\Actions\Recurrences\MaterializeRecurrences;
use App\Actions\Todos\StartDay;
use App\Enums\ListType;
use App\Http\Resources\TodoListResource;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use App\Models\User;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyListController extends Controller
{
    public function __construct(
        private readonly MaterializeRecurrences $materializeRecurrences,
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
            ->with(['todos.tags', 'todos.lists', 'todos.subTodos'])
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

            // Older unfinished todos: once sat on a daily list before the
            // previous workday and never carried forward to it or today.
            $earlierCandidates = $this->earlierCandidates($user, $previousWorkday);

            // Todos the user already scheduled onto this day before starting it.
            if ($list) {
                $preScheduled = $list->todos()->active()->with('tags')->get();
            }

            $masterOpenTodos = $user->todos()->active()->with('tags')->latest()->limit(50)->get();
        }

        $resolvedList = (! $needsRitual && $list)
            ? TodoListResource::make($list)->resolve()
            : null;

        return Inertia::render('lists/Day', [
            'date' => $date->toDateString(),
            'isToday' => $isToday,
            'list' => $resolvedList,
            'needsRitual' => $needsRitual,
            'previousWorkday' => $previousWorkday->toDateString(),
            'carryOverCandidates' => TodoResource::collection($carryOverCandidates)->resolve(),
            'earlierCandidates' => TodoResource::collection($earlierCandidates)->resolve(),
            'masterOpenTodos' => TodoResource::collection($masterOpenTodos)->resolve(),
            'preScheduled' => TodoResource::collection($preScheduled)->resolve(),
        ]);
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
