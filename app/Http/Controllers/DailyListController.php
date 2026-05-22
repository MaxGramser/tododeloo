<?php

namespace App\Http\Controllers;

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
        $list = $user->lists()
            ->where('type', ListType::Daily)
            ->whereDate('date', $date)
            ->with(['todos.tags', 'todos.lists', 'todos.subTodos'])
            ->first();

        $isToday = $date->isSameDay(CarbonImmutable::today());
        $needsRitual = $isToday && ($list === null || $list->started_at === null);

        $previousWorkday = Workday::lastWorkdayBefore($date);
        $carryOverCandidates = collect();
        $masterOpenTodos = collect();
        $preScheduled = collect();

        if ($needsRitual) {
            $previousList = $user->lists()
                ->where('type', ListType::Daily)
                ->whereDate('date', $previousWorkday)
                ->first();

            if ($previousList) {
                $carryOverCandidates = $previousList->todos()->active()->with('tags')->get();
            }

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
            'masterOpenTodos' => TodoResource::collection($masterOpenTodos)->resolve(),
            'preScheduled' => TodoResource::collection($preScheduled)->resolve(),
        ]);
    }
}
