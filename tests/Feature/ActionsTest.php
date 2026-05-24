<?php

use App\Actions\Lists\CreateCustomList;
use App\Actions\Lists\EnsureMasterList;
use App\Actions\Lists\GetOrCreateDailyList;
use App\Actions\Lists\ReorderListItems;
use App\Actions\Lists\UpdateListSortMode;
use App\Actions\Todos\AddTodoToList;
use App\Actions\Todos\BuildRitualCandidates;
use App\Actions\Todos\CarryOverTodos;
use App\Actions\Todos\CompleteTodo;
use App\Actions\Todos\CreateTodo;
use App\Actions\Todos\DeleteTodo;
use App\Actions\Todos\QuickAddTodo;
use App\Actions\Todos\RemoveTodoFromList;
use App\Actions\Todos\RestoreTodo;
use App\Actions\Todos\UncompleteTodo;
use App\Enums\Priority;
use App\Enums\SortMode;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('auto-creates a master list when a user is created', function () {
    expect($this->user->masterList)->not->toBeNull()
        ->and($this->user->masterList->isMaster())->toBeTrue();
});

it('EnsureMasterList is idempotent', function () {
    $action = app(EnsureMasterList::class);
    $a = $action($this->user);
    $b = $action($this->user);

    expect($a->id)->toBe($b->id)
        ->and($this->user->lists()->count())->toBe(1);
});

it('GetOrCreateDailyList creates exactly one list per date', function () {
    $action = app(GetOrCreateDailyList::class);
    $date = CarbonImmutable::create(2026, 5, 20);

    $a = $action($this->user, $date);
    $b = $action($this->user, $date);

    expect($a->id)->toBe($b->id);
});

it('CreateTodo attaches to master automatically', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Test todo']);

    expect($todo->title)->toBe('Test todo')
        ->and($this->user->masterList->todos)->toHaveCount(1)
        ->and($this->user->masterList->todos->first()->id)->toBe($todo->id);
});

it('CreateTodo attaches to additional list and master', function () {
    $daily = app(GetOrCreateDailyList::class)($this->user, today());

    $todo = app(CreateTodo::class)($this->user, ['title' => 'Test'], $daily);

    expect($todo->lists)->toHaveCount(2);
});

it('AddTodoToList is idempotent', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Test']);
    $custom = app(CreateCustomList::class)($this->user, 'Boodschappen');

    app(AddTodoToList::class)($todo, $custom);
    app(AddTodoToList::class)($todo, $custom);

    expect($custom->items()->count())->toBe(1);
});

it('AddTodoToList places new items at the end of the list', function () {
    $list = app(CreateCustomList::class)($this->user, 'L');
    $first = app(CreateTodo::class)($this->user, ['title' => 'A']);
    $second = app(CreateTodo::class)($this->user, ['title' => 'B']);

    app(AddTodoToList::class)($first, $list);
    app(AddTodoToList::class)($second, $list);

    $positions = $list->items()->orderBy('position')->pluck('todo_id')->all();
    expect($positions)->toBe([$first->id, $second->id]);
});

it('RemoveTodoFromList unlinks from a custom list but keeps master intact', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);
    $custom = app(CreateCustomList::class)($this->user, 'Boodschappen');
    app(AddTodoToList::class)($todo, $custom);

    app(RemoveTodoFromList::class)($todo, $custom);

    expect($custom->fresh()->todos)->toHaveCount(0)
        ->and($this->user->masterList->todos)->toHaveCount(1);
});

it('RemoveTodoFromList refuses to unlink from master', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);

    expect(fn () => app(RemoveTodoFromList::class)($todo, $this->user->masterList))
        ->toThrow(InvalidArgumentException::class);
});

it('CompleteTodo and UncompleteTodo toggle completed_at', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);

    $completed = app(CompleteTodo::class)($todo);
    expect($completed->isCompleted())->toBeTrue();

    $reopened = app(UncompleteTodo::class)($completed);
    expect($reopened->isCompleted())->toBeFalse();
});

it('completion status is visible across all lists', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);
    $custom = app(CreateCustomList::class)($this->user, 'Project');
    app(AddTodoToList::class)($todo, $custom);

    app(CompleteTodo::class)($todo);

    expect($this->user->masterList->todos()->first()->isCompleted())->toBeTrue()
        ->and($custom->fresh()->todos()->first()->isCompleted())->toBeTrue();
});

it('DeleteTodo soft-deletes and RestoreTodo brings it back into lists', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'X']);

    app(DeleteTodo::class)($todo);
    expect($this->user->masterList->todos)->toHaveCount(0)
        ->and(Todo::withTrashed()->count())->toBe(1);

    app(RestoreTodo::class)($todo);
    expect($this->user->masterList()->first()->todos)->toHaveCount(1);
});

it('QuickAddTodo on a weekday targets today', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 10, 0));

    $result = app(QuickAddTodo::class)($this->user, 'Snel');

    expect($result['target_date']->toDateString())->toBe('2026-05-20')
        ->and($result['todo']->lists)->toHaveCount(2);

    CarbonImmutable::setTestNow();
});

it('QuickAddTodo on a Saturday targets the upcoming Monday', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 23, 10, 0));

    $result = app(QuickAddTodo::class)($this->user, 'Weekend gedachte');

    expect($result['target_date']->toDateString())->toBe('2026-05-25');

    $monday = TodoList::query()
        ->where('user_id', $this->user->id)
        ->whereDate('date', '2026-05-25')
        ->first();
    expect($monday)->not->toBeNull()
        ->and($monday->todos)->toHaveCount(1);

    CarbonImmutable::setTestNow();
});

it('CarryOverTodos only attaches active todos', function () {
    $active = app(CreateTodo::class)($this->user, ['title' => 'open']);
    $done = app(CreateTodo::class)($this->user, ['title' => 'gedaan']);
    app(CompleteTodo::class)($done);

    $list = app(CarryOverTodos::class)($this->user, today(), [$active->id, $done->id]);

    expect($list->todos)->toHaveCount(1)
        ->and($list->todos->first()->id)->toBe($active->id);
});

it('keeps a completed future-scheduled todo out of the morning ritual', function () {
    $today = CarbonImmutable::create(2026, 5, 20);
    $tuesday = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 26));

    // Two todos scheduled for next Tuesday; one is ticked off ahead of its date.
    $done = app(CreateTodo::class)($this->user, ['title' => 'bunq rekeningen checken'], $tuesday);
    app(CompleteTodo::class)($done);
    $open = app(CreateTodo::class)($this->user, ['title' => 'abonnementen opzeggen'], $tuesday);

    $todayList = app(GetOrCreateDailyList::class)($this->user, $today);
    $buckets = app(BuildRitualCandidates::class)($this->user, $today, $todayList);

    $ids = collect($buckets['preScheduled'])
        ->concat($buckets['carryOverCandidates'])
        ->concat($buckets['earlierCandidates'])
        ->concat($buckets['masterOpenTodos'])
        ->pluck('id');

    expect($ids)->not->toContain($done->id) // completed → never surfaces
        ->and($ids)->toContain($open->id);  // still-open control → does surface
});

it('ReorderListItems updates positions in given order', function () {
    $list = app(CreateCustomList::class)($this->user, 'L');
    $a = app(CreateTodo::class)($this->user, ['title' => 'A']);
    $b = app(CreateTodo::class)($this->user, ['title' => 'B']);
    $c = app(CreateTodo::class)($this->user, ['title' => 'C']);
    app(AddTodoToList::class)($a, $list);
    app(AddTodoToList::class)($b, $list);
    app(AddTodoToList::class)($c, $list);

    app(ReorderListItems::class)($list, [$c->id, $a->id, $b->id]);

    $ordered = $list->items()->orderBy('position')->pluck('todo_id')->all();
    expect($ordered)->toBe([$c->id, $a->id, $b->id]);
});

it('UpdateListSortMode freezes visible order when switching to manual', function () {
    $list = app(CreateCustomList::class)($this->user, 'L');
    $a = app(CreateTodo::class)($this->user, ['title' => 'A']);
    $b = app(CreateTodo::class)($this->user, ['title' => 'B']);
    app(AddTodoToList::class)($a, $list);
    app(AddTodoToList::class)($b, $list);

    app(UpdateListSortMode::class)($list, SortMode::Alphabetical);
    expect($list->fresh()->sort_mode)->toBe(SortMode::Alphabetical);

    app(UpdateListSortMode::class)($list, SortMode::Manual, [$b->id, $a->id]);
    $ordered = $list->items()->orderBy('position')->pluck('todo_id')->all();
    expect($ordered)->toBe([$b->id, $a->id]);
});

it('priority is honoured on create', function () {
    $todo = app(CreateTodo::class)($this->user, [
        'title' => 'Belangrijk',
        'priority' => Priority::High,
    ]);

    expect($todo->priority)->toBe(Priority::High);
});
