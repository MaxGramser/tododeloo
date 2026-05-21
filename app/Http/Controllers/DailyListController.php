<?php

namespace App\Http\Controllers;

use App\Actions\Todos\CarryOverTodos;
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

    public function carryOver(Request $request, CarryOverTodos $carryOver, string $date): RedirectResponse
    {
        $validated = $request->validate([
            'todo_ids' => 'array',
            'todo_ids.*' => 'integer',
        ]);

        $carryOver($request->user(), CarbonImmutable::parse($date), $validated['todo_ids'] ?? []);

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

        $previousWorkday = Workday::lastWorkdayBefore($date);
        $previousList = $user->lists()
            ->where('type', ListType::Daily)
            ->whereDate('date', $previousWorkday)
            ->first();

        $carryOverCandidates = collect();
        if ($previousList) {
            $carryOverCandidates = $previousList->todos()->active()->with('tags')->get();
        }

        $needsRitual = $list === null;
        $resolvedList = $list ? TodoListResource::make($list->load(['todos.tags', 'todos.lists', 'todos.subTodos']))->resolve() : null;

        return Inertia::render('lists/Day', [
            'date' => $date->toDateString(),
            'isToday' => $date->isSameDay(CarbonImmutable::today()),
            'list' => $resolvedList,
            'needsRitual' => $needsRitual,
            'previousWorkday' => $previousWorkday->toDateString(),
            'carryOverCandidates' => TodoResource::collection($carryOverCandidates)->resolve(),
            'masterOpenTodos' => TodoResource::collection(
                $user->todos()->active()->with('tags')->latest()->limit(50)->get()
            )->resolve(),
        ]);
    }
}
