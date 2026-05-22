<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailyController;
use App\Http\Controllers\Api\ListController;
use App\Http\Controllers\Api\QuickAddController;
use App\Http\Controllers\Api\SubTodoController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TodoController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');

    Route::get('today', [DailyController::class, 'today'])->name('today');
    Route::get('days/{date}', [DailyController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('days.show');
    Route::post('days/{date}/start', [DailyController::class, 'start'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('days.start');

    Route::get('lists', [ListController::class, 'index'])->name('lists.index');
    Route::get('master', [ListController::class, 'master'])->name('master');
    Route::post('lists', [ListController::class, 'store'])->name('lists.store');
    Route::get('lists/{list}', [ListController::class, 'show'])->name('lists.show');
    Route::patch('lists/{list}', [ListController::class, 'update'])->name('lists.update');
    Route::delete('lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');
    Route::post('lists/{list}/reorder', [ListController::class, 'reorder'])->name('lists.reorder');
    Route::post('lists/{list}/sort-mode', [ListController::class, 'sortMode'])->name('lists.sort-mode');

    Route::post('todos', [TodoController::class, 'store'])->name('todos.store');
    Route::patch('todos/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::delete('todos/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');
    Route::post('todos/{todo}/restore', [TodoController::class, 'restore'])
        ->withTrashed()
        ->name('todos.restore');
    Route::post('todos/{todo}/complete', [TodoController::class, 'complete'])->name('todos.complete');
    Route::post('todos/{todo}/uncomplete', [TodoController::class, 'uncomplete'])->name('todos.uncomplete');
    Route::post('todos/{todo}/lists/{list}', [TodoController::class, 'addToList'])->name('todos.add-to-list');
    Route::delete('todos/{todo}/lists/{list}', [TodoController::class, 'removeFromList'])->name('todos.remove-from-list');
    Route::post('todos/{todo}/add-to-today', [TodoController::class, 'addToToday'])->name('todos.add-to-today');
    Route::post('todos/{todo}/duplicate', [TodoController::class, 'duplicate'])->name('todos.duplicate');
    Route::post('todos/{todo}/move-to-date', [TodoController::class, 'moveToDate'])->name('todos.move-to-date');
    Route::patch('todos/{todo}/tags', [TodoController::class, 'syncTags'])->name('todos.tags.sync');

    Route::post('todos/{todo}/sub-todos', [SubTodoController::class, 'store'])->name('sub-todos.store');
    Route::patch('sub-todos/{subTodo}', [SubTodoController::class, 'update'])->name('sub-todos.update');
    Route::post('sub-todos/{subTodo}/toggle', [SubTodoController::class, 'toggle'])->name('sub-todos.toggle');
    Route::delete('sub-todos/{subTodo}', [SubTodoController::class, 'destroy'])->name('sub-todos.destroy');

    Route::post('quick-add', QuickAddController::class)->name('quick-add');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
});
