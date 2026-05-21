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
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'title', 'description', 'priority', 'completed_at'])]
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'priority' => Priority::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
