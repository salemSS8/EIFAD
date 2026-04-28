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
        Schema::table('jobdemandsnapshot', function (Blueprint $table) {
            $table->unsignedBigInteger('industry_id')->nullable()->after('JobTitle');
            $table->string('city_name')->nullable()->after('industry_id');

            $table->index(['industry_id', 'city_name', 'SnapshotDate'], 'job_snapshot_filters_index');
            $table->foreign('industry_id')->references('id')->on('industries')->nullOnDelete();
        });

        Schema::table('skilldemandsnapshot', function (Blueprint $table) {
            $table->unsignedBigInteger('industry_id')->nullable()->after('SkillID');
            $table->string('city_name')->nullable()->after('industry_id');

            $table->index(['industry_id', 'city_name', 'SnapshotDate'], 'skill_snapshot_filters_index');
            $table->foreign('industry_id')->references('id')->on('industries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobdemandsnapshot', function (Blueprint $table) {
            $table->dropForeign(['industry_id']);
            $table->dropIndex('job_snapshot_filters_index');
            $table->dropColumn(['industry_id', 'city_name']);
        });

        Schema::table('skilldemandsnapshot', function (Blueprint $table) {
            $table->dropForeign(['industry_id']);
            $table->dropIndex('skill_snapshot_filters_index');
            $table->dropColumn(['industry_id', 'city_name']);
        });
    }
};
