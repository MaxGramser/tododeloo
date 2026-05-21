<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_todo', function (Blueprint $table) {
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['todo_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_todo');
    }
};
