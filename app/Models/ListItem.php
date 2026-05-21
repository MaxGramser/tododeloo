<?php

namespace App\Models;

use Database\Factories\ListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['todo_list_id', 'todo_id', 'position', 'added_at'])]
class ListItem extends Pivot
{
    /** @use HasFactory<ListItemFactory> */
    use HasFactory;

    protected $table = 'list_items';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'todo_list_id');
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }
}
