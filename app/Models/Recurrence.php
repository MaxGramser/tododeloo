<?php

namespace App\Models;

use App\Enums\Priority;
use App\Support\RecurrenceSchedule;
use Database\Factories\RecurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'title', 'priority', 'rrule', 'dtstart', 'until', 'active', 'last_generated_on'])]
class Recurrence extends Model
{
    /** @use HasFactory<RecurrenceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'dtstart' => 'date',
            'until' => 'date',
            'active' => 'boolean',
            'last_generated_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function schedule(): RecurrenceSchedule
    {
        return new RecurrenceSchedule($this->rrule, $this->dtstart, $this->until);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
