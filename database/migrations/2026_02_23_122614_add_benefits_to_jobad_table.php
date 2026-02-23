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
            $table->text('Benefits')->nullable()->after('Requirements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobad', function (Blueprint $table) {
            $table->dropColumn('Benefits');
        });
    }
};
