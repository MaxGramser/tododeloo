<?php

namespace App\Models;

use Database\Factories\SubTodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['todo_id', 'title', 'completed_at', 'position'])]
class SubTodo extends Model
{
    /** @use HasFactory<SubTodoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
