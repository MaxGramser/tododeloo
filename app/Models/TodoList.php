<?php

namespace App\Models;

use App\Enums\ListType;
use App\Enums\SortMode;
use Database\Factories\TodoListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'type', 'name', 'date', 'sort_mode'])]
class TodoList extends Model
{
    /** @use HasFactory<TodoListFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ListType::class,
            'sort_mode' => SortMode::class,
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class);
    }

    public function todos(): BelongsToMany
    {
        return $this->belongsToMany(Todo::class, 'list_items')
            ->using(ListItem::class)
            ->withPivot(['position', 'added_at'])
            ->withTimestamps();
    }

    public function isMaster(): bool
    {
        return $this->type === ListType::Master;
    }

    public function isDaily(): bool
    {
        return $this->type === ListType::Daily;
    }

    public function isCustom(): bool
    {
        return $this->type === ListType::Custom;
    }

    public function scopeOfType(Builder $query, ListType $type): void
    {
        $query->where('type', $type);
    }

    public function scopeForDate(Builder $query, \DateTimeInterface $date): void
    {
        $query->where('type', ListType::Daily)->whereDate('date', $date);
    }
}
