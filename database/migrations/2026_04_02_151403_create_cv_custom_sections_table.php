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
        Schema::create('cv_custom_sections', function (Blueprint $table) {
            $table->id('CustomSectionID');
            $table->integer('CVID')->index();
            $table->string('SectionType'); // e.g., 'Volunteering', 'Awards', 'Projects', 'Certifications'
            $table->string('Title'); // e.g., 'Red Cross Volunteer', 'Best Developer Award'
            $table->text('Description')->nullable();
            $table->date('StartDate')->nullable();
            $table->date('EndDate')->nullable();
            $table->timestamps();

            $table->foreign('CVID')->references('CVID')->on('cv')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_custom_sections');
    }
};
