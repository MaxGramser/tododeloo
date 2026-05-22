<?php

use App\Actions\Lists\CreateCustomList;
use App\Actions\Lists\GetOrCreateDailyList;
use App\Actions\Todos\AddTodoToList;
use App\Actions\Todos\CreateTodo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('quick-adds a todo to today on a weekday', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 9, 0));

    $this->postJson(route('api.quick-add'), ['title' => 'Snel'])
        ->assertOk()
        ->assertJsonPath('todo.title', 'Snel')
        ->assertJsonPath('target_date', '2026-05-20');

    $today = $this->user->lists()->whereDate('date', '2026-05-20')->first();
    expect($today)->not->toBeNull()
        ->and($today->todos)->toHaveCount(1);

    CarbonImmutable::setTestNow();
});

it('shows today with a morning ritual when the day has not started', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));

    $this->getJson(route('api.today'))
        ->assertOk()
        ->assertJsonPath('is_today', true)
        ->assertJsonPath('needs_ritual', true)
        ->assertJsonPath('previous_workday', '2026-05-19');

    CarbonImmutable::setTestNow();
});

it('starts the day carrying selected todos plus new ones', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 20, 8, 0));
    $previous = app(GetOrCreateDailyList::class)($this->user, CarbonImmutable::create(2026, 5, 19));
    $carry = app(CreateTodo::class)($this->user, ['title' => 'Pak op']);
    app(AddTodoToList::class)($carry, $previous);

    $this->postJson(route('api.days.start', '2026-05-20'), [
        'carry_over_ids' => [$carry->id],
        'new_titles' => ['Nieuw vandaag'],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'list.todos');

    CarbonImmutable::setTestNow();
});

it('creates a todo and attaches it to master', function () {
    $this->postJson(route('api.todos.store'), ['title' => 'Boodschappen'])
        ->assertOk()
        ->assertJsonPath('todo.title', 'Boodschappen');

    expect($this->user->masterList->todos)->toHaveCount(1);
});

it('completes and uncompletes a todo', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Iets']);

    $this->postJson(route('api.todos.complete', $todo))->assertOk();
    expect($todo->fresh()->isCompleted())->toBeTrue();

    $this->postJson(route('api.todos.uncomplete', $todo))->assertOk();
    expect($todo->fresh()->isCompleted())->toBeFalse();
});

it('blocks acting on another user\'s todo', function () {
    $other = User::factory()->create();
    $otherTodo = app(CreateTodo::class)($other, ['title' => 'Hun todo']);

    $this->postJson(route('api.todos.complete', $otherTodo))->assertForbidden();
    $this->deleteJson(route('api.todos.destroy', $otherTodo))->assertForbidden();
});

it('lists master and custom lists with open counts', function () {
    $custom = app(CreateCustomList::class)($this->user, 'Project');
    app(CreateTodo::class)($this->user, ['title' => 'A'], $custom);

    $this->getJson(route('api.lists.index'))
        ->assertOk()
        ->assertJsonCount(2, 'lists');
});

it('shows a custom list with its todos', function () {
    $custom = app(CreateCustomList::class)($this->user, 'Project');
    app(CreateTodo::class)($this->user, ['title' => 'Plan'], $custom);

    $this->getJson(route('api.lists.show', $custom))
        ->assertOk()
        ->assertJsonPath('list.name', 'Project')
        ->assertJsonCount(1, 'list.todos');
});

it('adds and toggles sub-todos returning the parent todo', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Parent']);

    $this->postJson(route('api.sub-todos.store', $todo), ['title' => 'Sub'])
        ->assertOk()
        ->assertJsonPath('todo.sub_todos.0.title', 'Sub');

    $subId = $todo->fresh()->subTodos->first()->id;
    $this->postJson(route('api.sub-todos.toggle', $subId))->assertOk();

    expect($todo->fresh()->subTodos->first()->isCompleted())->toBeTrue();
});

it('creates and lists tags', function () {
    $this->postJson(route('api.tags.store'), ['name' => 'dringend'])
        ->assertOk()
        ->assertJsonPath('tag.name', 'dringend');

    $this->getJson(route('api.tags.index'))
        ->assertOk()
        ->assertJsonCount(1, 'tags');
});
