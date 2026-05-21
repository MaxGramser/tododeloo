<?php

use App\Enums\ListType;
use App\Enums\Priority;
use App\Models\Tag;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('auto-creates a master list scoped to user', function () {
    $user = User::factory()->create();

    expect($user->masterList)->not->toBeNull()
        ->and($user->masterList->isMaster())->toBeTrue()
        ->and($user->masterList->type)->toBe(ListType::Master);
});

it('attaches a todo to multiple lists via list_items', function () {
    $user = User::factory()->create();
    $master = TodoList::factory()->master()->for($user)->create();
    $daily = TodoList::factory()->daily()->for($user)->create();
    $todo = Todo::factory()->for($user)->create();

    $master->todos()->attach($todo, ['position' => 0, 'added_at' => now()]);
    $daily->todos()->attach($todo, ['position' => 0, 'added_at' => now()]);

    expect($todo->lists)->toHaveCount(2)
        ->and($master->todos)->toHaveCount(1)
        ->and($daily->todos)->toHaveCount(1);
});

it('reflects completion status on the todo across all lists', function () {
    $user = User::factory()->create();
    $master = TodoList::factory()->master()->for($user)->create();
    $daily = TodoList::factory()->daily()->for($user)->create();
    $todo = Todo::factory()->for($user)->create();

    $master->todos()->attach($todo, ['position' => 0, 'added_at' => now()]);
    $daily->todos()->attach($todo, ['position' => 0, 'added_at' => now()]);

    $todo->update(['completed_at' => now()]);

    expect($master->todos()->first()->isCompleted())->toBeTrue()
        ->and($daily->todos()->first()->isCompleted())->toBeTrue();
});

it('soft deletes todos', function () {
    $todo = Todo::factory()->create();
    $todo->delete();

    expect(Todo::find($todo->id))->toBeNull()
        ->and(Todo::withTrashed()->find($todo->id))->not->toBeNull();
});

it('enforces unique daily list per user per date', function () {
    $user = User::factory()->create();
    $date = today();
    TodoList::factory()->daily($date)->for($user)->create();

    expect(fn () => TodoList::factory()->daily($date)->for($user)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('casts priority to enum', function () {
    $todo = Todo::factory()->priority(Priority::High)->create();

    expect($todo->priority)->toBe(Priority::High);
});

it('attaches tags to todos', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();

    $todo->tags()->attach($tag);

    expect($todo->tags)->toHaveCount(1)
        ->and($tag->todos)->toHaveCount(1);
});

it('scopes active and completed todos', function () {
    $user = User::factory()->create();
    Todo::factory()->for($user)->create();
    Todo::factory()->for($user)->completed()->create();

    expect(Todo::active()->count())->toBe(1)
        ->and(Todo::completed()->count())->toBe(1);
});
