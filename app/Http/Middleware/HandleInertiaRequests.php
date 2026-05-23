<?php

namespace App\Http\Middleware;

use App\Actions\Todos\BuildUpcomingSchedule;
use App\Enums\ListType;
use App\Http\Resources\TagResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sidebarLists' => fn () => $this->resolveSidebarLists($request),
            'userTags' => fn () => $request->user()
                ? TagResource::collection($request->user()->tags()->orderBy('name')->get())->resolve()
                : [],
            'today' => CarbonImmutable::today()->toDateString(),
            'isLocal' => ! app()->environment('production'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSidebarLists(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $today = CarbonImmutable::today();

        $todayList = $user->lists()
            ->where('type', ListType::Daily)
            ->whereDate('date', $today)
            ->first();

        $upcoming = app(BuildUpcomingSchedule::class)($user, $today, maxDays: 5, withTodos: false, onlyOpen: false);

        $customs = $user->lists()
            ->where('type', ListType::Custom)
            ->orderBy('name')
            ->get();

        return [
            'master' => [
                'href' => route('master.show'),
            ],
            'today' => [
                'id' => $todayList?->id,
                'date' => $today->toDateString(),
                'href' => route('today.show'),
            ],
            'customs' => $customs->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'href' => route('lists.show', $list),
            ])->values()->all(),
            'upcomingDailies' => $upcoming->map(fn ($list) => [
                'id' => $list->id,
                'date' => $list->date->toDateString(),
                'href' => route('day.show', $list->date->toDateString()),
            ])->values()->all(),
        ];
    }
}
