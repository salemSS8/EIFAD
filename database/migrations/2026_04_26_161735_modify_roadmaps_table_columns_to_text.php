<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roadmaps', function (Blueprint $table) {
            $table->text('title')->nullable()->change();
            $table->text('current_level')->nullable()->change();
            $table->text('target_level')->nullable()->change();
            $table->text('total_estimated_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roadmaps', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->string('current_level')->nullable()->change();
            $table->string('target_level')->nullable()->change();
            $table->string('total_estimated_time')->nullable()->change();
        });
    }
};
