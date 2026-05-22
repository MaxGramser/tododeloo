<?php

use App\Http\Controllers\CustomListController;
use App\Http\Controllers\DailyListController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevQuickLoginController;
use App\Http\Controllers\ListReorderController;
use App\Http\Controllers\ListSortModeController;
use App\Http\Controllers\MasterListController;
use App\Http\Controllers\QuickAddController;
use App\Http\Controllers\SubTodoController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

if (! app()->environment('production')) {
    Route::post('__dev/quick-login', DevQuickLoginController::class)->name('dev.quick-login');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('master', MasterListController::class)->name('master.show');

    Route::get('today', [DailyListController::class, 'today'])->name('today.show');
    Route::get('day/{date}', [DailyListController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('day.show');
    Route::post('day/{date}/start', [DailyListController::class, 'start'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('day.start');

    Route::post('lists', [CustomListController::class, 'store'])->name('lists.store');
    Route::get('lists/{list}', [CustomListController::class, 'show'])->name('lists.show');
    Route::patch('lists/{list}', [CustomListController::class, 'update'])->name('lists.update');
    Route::delete('lists/{list}', [CustomListController::class, 'destroy'])->name('lists.destroy');

    Route::post('lists/{list}/reorder', ListReorderController::class)->name('lists.reorder');
    Route::post('lists/{list}/sort-mode', ListSortModeController::class)->name('lists.sort-mode');

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

    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
});

require __DIR__.'/settings.php';
