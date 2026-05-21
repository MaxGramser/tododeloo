<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name')->nullable();
            $table->date('date')->nullable();
            $table->string('sort_mode')->default('created_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
            $table->unique(['user_id', 'date'], 'todo_lists_user_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_lists');
    }
};
