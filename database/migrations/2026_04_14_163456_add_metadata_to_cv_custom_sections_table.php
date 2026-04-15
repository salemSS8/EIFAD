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
        Schema::table('cv_custom_sections', function (Blueprint $table) {
            $table->json('content_data')->nullable()->after('Description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cv_custom_sections', function (Blueprint $table) {
            $table->dropColumn('content_data');
        });
    }
};
