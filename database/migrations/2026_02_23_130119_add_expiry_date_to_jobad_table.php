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
        Schema::table('jobad', function (Blueprint $table) {
            $table->dateTime('ExpiryDate')->nullable()->after('PostedAt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobad', function (Blueprint $table) {
            $table->dropColumn('ExpiryDate');
        });
    }
};
