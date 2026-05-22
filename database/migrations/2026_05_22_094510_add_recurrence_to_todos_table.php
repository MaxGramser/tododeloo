<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('recurrence_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->date('occurred_on')->nullable()->after('recurrence_id');

            // One materialized instance per recurrence per date.
            $table->unique(['recurrence_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropUnique(['recurrence_id', 'occurred_on']);
            $table->dropConstrainedForeignId('recurrence_id');
            $table->dropColumn('occurred_on');
        });
    }
};
