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
        Schema::table('jobskill', function (Blueprint $blueprint) {
            // Drop existing foreign key
            $blueprint->dropForeign(['JobAdID']);

            // Re-add with cascade
            $blueprint->foreign('JobAdID')
                ->references('JobAdID')
                ->on('jobad')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobskill', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['JobAdID']);

            $blueprint->foreign('JobAdID')
                ->references('JobAdID')
                ->on('jobad');
        });
    }
};
