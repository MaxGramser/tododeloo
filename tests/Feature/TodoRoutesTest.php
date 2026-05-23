<?php

use App\Actions\Lists\CreateCustomList;
use App\Actions\Lists\EnsureMasterList;
use App\Actions\Lists\GetOrCreateDailyList;
use App\Actions\Todos\AddTodoToList;
use App\Actions\Todos\CreateTodo;
use App\Enums\Priority;
use App\Enums\SortMode;
use App\Models\Recurrence;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('rejects unauthenticated access to lists', function () {
    auth()->logout();
    $this->get(route('master.show'))->assertRedirect(route('login'));
});

it('renders the master list page with todos', function () {
    app(CreateTodo::class)($this->user, ['title' => 'Hallo']);

    $this->get(route('master.show'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('lists/Master')
                ->has('list.todos', 1)
                ->where('list.todos.0.title', 'Hallo'),
        );
});

it('stores a new todo via POST /todos and attaches it to master', function () {
    $this->post(route('todos.store'), ['title' => 'Boodschappen'])
        ->assertRedirect();

    expect($this->user->masterList->todos)->toHaveCount(1)
        ->and($this->user->masterList->todos->first()->title)->toBe('Boodschappen');
});

it('stores a todo into an additional list', function () {
    $custom = app(CreateCustomList::class)($this->user, 'Project');

    $this->post(route('todos.store'), [
        'title' => 'Plan opstellen',
        'list_id' => $custom->id,
    ])->assertRedirect();

    expect($custom->fresh()->todos)->toHaveCount(1);
});

it('blocks adding a todo to a list owned by another user', function () {
    $otherUser = User::factory()->create();
    $otherList = app(CreateCustomList::class)($otherUser, 'Hun lijst');

    $this->post(route('todos.store'), [
        'title' => 'Doe dit niet',
        'list_id' => $otherList->id,
    ])->assertForbidden();
});

it('toggles a todo via complete and uncomplete endpoints', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Iets']);

    $this->post(route('todos.complete', $todo))->assertRedirect();
    expect($todo->fresh()->isCompleted())->toBeTrue();

    $this->post(route('todos.uncomplete', $todo))->assertRedirect();
    expect($todo->fresh()->isCompleted())->toBeFalse();
});

it('soft-deletes a todo and supports restore', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Verdwijn']);

    $this->delete(route('todos.destroy', $todo))->assertRedirect();
    expect(Todo::find($todo->id))->toBeNull();

    $this->post(route('todos.restore', $todo->id))->assertRedirect();
    expect(Todo::find($todo->id))->not->toBeNull();
});

it('blocks operating on another user\'s todo', function () {
    $otherUser = User::factory()->create();
    $otherTodo = app(CreateTodo::class)($otherUser, ['title' => 'Hun todo']);

    $this->post(route('todos.complete', $otherTodo))->assertForbidden();
    $this->delete(route('todos.destroy', $otherTodo))->assertForbidden();
});

it('removes a todo from a custom list but keeps it on master', function () {
    $custom = app(CreateCustomList::class)($this->user, 'Project');
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X'], $custom);

    $this->delete(route('todos.remove-from-list', ['todo' => $todo, 'list' => $custom]))
        ->assertRedirect();

    expect($custom->fresh()->todos)->toHaveCount(0)
        ->and($this->user->masterList->todos)->toHaveCount(1);
});

it('refuses to remove a todo from its master list', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);
    $this->withoutExceptionHandling();

    expect(
        fn () => $this->delete(
            route('todos.remove-from-list', ['todo' => $todo, 'list' => $this->user->masterList]),
        ),
    )->toThrow(InvalidArgumentException::class);
});

it('renders the day page with a morning ritual when today has not started', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));
    $previous = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 19));
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Niet af gisteren']);
    app(AddTodoToList::class)($todo, $previous);

    $this->get(route('today.show'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('lists/Day')
                ->where('needsRitual', true)
                ->where('previousWorkday', '2026-05-19')
                ->has('carryOverCandidates', 1),
        );

    CarbonImmutable::setTestNow();
});

it('still shows the ritual today even when todos were pre-scheduled', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));
    $today = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::today());
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Vooruit gepland']);
    app(AddTodoToList::class)($todo, $today);

    $this->get(route('today.show'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('lists/Day')
                ->where('needsRitual', true)
                ->has('preScheduled', 1)
                ->where('preScheduled.0.title', 'Vooruit gepland'),
        );

    CarbonImmutable::setTestNow();
});

it('never repeats a carry-over todo in the master bucket', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));
    $previous = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 19));
    $carry = app(CreateTodo::class)($this->user, ['title' => 'Niet af gisteren']);
    app(AddTodoToList::class)($carry, $previous);
    app(CreateTodo::class)($this->user, ['title' => 'Alleen op master']);

    $this->get(route('today.show'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('needsRitual', true)
                ->has('carryOverCandidates', 1)
                ->where('carryOverCandidates.0.title', 'Niet af gisteren')
                ->has('masterOpenTodos', 1)
                ->where('masterOpenTodos.0.title', 'Alleen op master'),
        );

    CarbonImmutable::setTestNow();
});

it('start marks the day as started and carries selected todos plus new ones', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));
    $previous = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 19));
    $carry = app(CreateTodo::class)($this->user, ['title' => 'Pak op']);
    app(AddTodoToList::class)($carry, $previous);

    $this->post(route('day.start', '2026-05-20'), [
        'carry_over_ids' => [$carry->id],
        'new_titles' => ['Nieuw vandaag'],
    ])->assertRedirect();

    $daily = $this->user->lists()->whereDate('date', '2026-05-20')->first();
    expect($daily)->not->toBeNull()
        ->and($daily->started_at)->not->toBeNull()
        ->and($daily->todos)->toHaveCount(2);

    // after starting, the ritual no longer fires
    $this->get(route('today.show'))
        ->assertInertia(fn ($page) => $page->where('needsRitual', false));

    CarbonImmutable::setTestNow();
});

it('reset re-opens the morning ritual for the day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));
    $today = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::today());
    $today->update(['started_at' => now()]);

    $this->get(route('today.show'))
        ->assertInertia(fn ($page) => $page->where('needsRitual', false));

    $this->post(route('day.reset', '2026-05-20'))->assertRedirect();

    expect($today->fresh()->started_at)->toBeNull();
    $this->get(route('today.show'))
        ->assertInertia(fn ($page) => $page->where('needsRitual', true));

    CarbonImmutable::setTestNow();
});

it('quick-adds a todo to today on a weekday', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));

    $this->post(route('quick-add'), ['title' => 'Snel'])->assertRedirect();

    $today = $this->user->lists()->whereDate('date', '2026-05-20')->first();
    expect($today)->not->toBeNull()
        ->and($today->todos)->toHaveCount(1);

    CarbonImmutable::setTestNow();
});

it('quick-adds to the current list (master) instead of today when given a context', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));
    $master = app(EnsureMasterList::class)($this->user);

    $this->post(route('quick-add'), ['title' => 'Begroting', 'list_id' => $master->id])->assertRedirect();

    expect($this->user->lists()->whereDate('date', '2026-05-20')->exists())->toBeFalse()
        ->and($master->fresh()->todos()->where('title', 'Begroting')->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('lets an explicit date in the quick-add text win over the page context', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));
    $master = app(EnsureMasterList::class)($this->user);

    $this->post(route('quick-add'), ['title' => 'morgen de auto wassen', 'list_id' => $master->id])->assertRedirect();

    $tomorrow = $this->user->lists()->whereDate('date', '2026-05-21')->first();
    expect($tomorrow)->not->toBeNull()
        ->and($tomorrow->todos()->where('title', 'de auto wassen')->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('creates a recurrence straight from a quick-add phrase', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));

    $this->post(route('quick-add'), ['title' => 'elke werkdag stand-up'])->assertRedirect();

    $recurrence = Recurrence::firstWhere('user_id', $this->user->id);
    expect($recurrence)->not->toBeNull()
        ->and($recurrence->rrule)->toBe('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR')
        ->and($this->user->todos()->where('title', 'stand-up')->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('lists upcoming days in the sidebar but skips days with no todos', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));

    $withTodo = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 22));
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Toekomst']);
    app(AddTodoToList::class)($todo, $withTodo);

    // an empty future day (e.g. left behind after its todos were deleted) must not show
    app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 23));

    $this->get(route('master.show'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('sidebarLists.upcomingDailies', 1)
                ->where('sidebarLists.upcomingDailies.0.date', '2026-05-22'),
        );

    CarbonImmutable::setTestNow();
});

it('drops a day from upcoming once its last todo is deleted', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));

    $day = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 22));
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Verdwijnt']);
    app(AddTodoToList::class)($todo, $day);

    $this->get(route('master.show'))
        ->assertInertia(fn ($page) => $page->has('sidebarLists.upcomingDailies', 1));

    $this->delete(route('todos.destroy', $todo))->assertRedirect();

    $this->get(route('master.show'))
        ->assertInertia(fn ($page) => $page->has('sidebarLists.upcomingDailies', 0));

    CarbonImmutable::setTestNow();
});

it('creates and shows a custom list', function () {
    $this->post(route('lists.store'), ['name' => 'Boodschappen'])
        ->assertRedirect();

    $list = $this->user->lists()->where('name', 'Boodschappen')->first();
    expect($list)->not->toBeNull();

    $this->get(route('lists.show', $list))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('lists/Custom'));
});

it('updates list sort mode and persists positions when switching to manual', function () {
    $list = app(CreateCustomList::class)($this->user, 'L');
    $a = app(CreateTodo::class)($this->user, ['title' => 'A'], $list);
    $b = app(CreateTodo::class)($this->user, ['title' => 'B'], $list);

    $this->post(route('lists.sort-mode', $list), [
        'sort_mode' => 'manual',
        'visible_todo_ids' => [$b->id, $a->id],
    ])->assertRedirect();

    expect($list->fresh()->sort_mode)->toBe(SortMode::Manual);
    $order = $list->items()->orderBy('position')->pluck('todo_id')->all();
    expect($order)->toBe([$b->id, $a->id]);
});

it('reorders a list manually', function () {
    $list = app(CreateCustomList::class)($this->user, 'L');
    $a = app(CreateTodo::class)($this->user, ['title' => 'A'], $list);
    $b = app(CreateTodo::class)($this->user, ['title' => 'B'], $list);
    $c = app(CreateTodo::class)($this->user, ['title' => 'C'], $list);

    $this->post(route('lists.reorder', $list), [
        'todo_ids' => [$c->id, $a->id, $b->id],
    ])->assertRedirect();

    $order = $list->items()->orderBy('position')->pluck('todo_id')->all();
    expect($order)->toBe([$c->id, $a->id, $b->id]);
});

it('honours priority when creating a todo via HTTP', function () {
    $this->post(route('todos.store'), [
        'title' => 'Belangrijk',
        'priority' => 'high',
    ])->assertRedirect();

    $todo = $this->user->todos()->first();
    expect($todo->priority)->toBe(Priority::High);
});

it('updates priority via patch', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);

    $this->patch(route('todos.update', $todo), ['priority' => 'high'])
        ->assertRedirect();

    expect($todo->fresh()->priority)->toBe(Priority::High);
});

it('creates and syncs tags on a todo', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Met tag']);

    $this->post(route('tags.store'), ['name' => 'dringend'])->assertRedirect();
    $tag = $this->user->tags()->first();

    $this->patch(route('todos.tags.sync', $todo), ['tag_ids' => [$tag->id]])
        ->assertRedirect();

    expect($todo->fresh()->tags)->toHaveCount(1)
        ->and($todo->fresh()->tags->first()->name)->toBe('dringend');
});

it('refuses to attach another users tag', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);
    $otherUser = User::factory()->create();
    $otherTag = $otherUser->tags()->create(['name' => 'verboden']);

    $this->patch(route('todos.tags.sync', $todo), ['tag_ids' => [$otherTag->id]])
        ->assertRedirect();

    expect($todo->fresh()->tags)->toHaveCount(0);
});
