<?php

namespace App\Http\Controllers\Api;

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
            'list' => TodoListResource::make($list->load(['todos.tags', 'todos.lists', 'todos.subTodos']))->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForDate(Request $request, CarbonImmutable $date): array
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
            'master_open_todos' => TodoResource::collection($masterOpenTodos)->resolve(),
            'pre_scheduled' => TodoResource::collection($preScheduled)->resolve(),
        ];
    }
}
