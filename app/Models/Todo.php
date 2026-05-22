<?php

namespace App\Models;

use App\Enums\Priority;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'recurrence_id', 'occurred_on', 'title', 'description', 'priority', 'completed_at'])]
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'occurred_on' => 'date',
            'priority' => Priority::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(TodoList::class, 'list_items')
            ->using(ListItem::class)
            ->withPivot(['position', 'added_at'])
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function subTodos(): HasMany
    {
        return $this->hasMany(SubTodo::class)->orderBy('position');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    /**
     * Exclude recurrence instances. They regenerate each occurrence, so they
     * must never be offered as carry-over candidates.
     */
    public function scopeNotRecurring(Builder $query): void
    {
        $query->whereNull('recurrence_id');
    }

    public function isRecurrenceInstance(): bool
    {
        return $this->recurrence_id !== null;
    }
}
