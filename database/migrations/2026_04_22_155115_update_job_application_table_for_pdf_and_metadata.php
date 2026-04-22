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
        Schema::table('jobapplication', function (Blueprint $table) {
            $table->string('CV')->nullable()->after('CVID');
            $table->string('JobSeekerName')->nullable()->after('JobSeekerID');
            $table->string('JobSeekerEmail')->nullable()->after('JobSeekerName');
            $table->string('JobSeekerPhone')->nullable()->after('JobSeekerEmail');
            $table->string('JobSeekerAddress')->nullable()->after('JobSeekerPhone');
            $table->text('AboutMe')->nullable()->after('MatchScore');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobapplication', function (Blueprint $table) {
            $table->dropColumn([
                'CV',
                'JobSeekerName',
                'JobSeekerEmail',
                'JobSeekerPhone',
                'JobSeekerAddress',
                'AboutMe',
            ]);
        });
    }
};
