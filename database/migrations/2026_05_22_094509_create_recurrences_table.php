<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('priority')->default('normal');
            $table->string('rrule');
            $table->date('dtstart');
            $table->date('until')->nullable();
            $table->boolean('active')->default(true);
            $table->date('last_generated_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrences');
    }
};
