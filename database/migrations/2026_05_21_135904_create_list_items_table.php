<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->unique(['todo_list_id', 'todo_id']);
            $table->index(['todo_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
