<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->smallInteger('page_count')->default(1);
            $table->jsonb('pages')->default(DB::raw("'[]'::jsonb"));
            $table->unsignedInteger('full_size')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['page_count']);
            $table->dropColumn(['pages']);
            $table->dropColumn(['full_size']);
        });
    }
};
