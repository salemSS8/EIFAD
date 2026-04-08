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
        Schema::create('cv_certifications', function (Blueprint $table) {
            $table->id('CertificationID');
            $table->integer('CVID')->index();
            $table->string('CertificateName');
            $table->string('IssuingOrganization')->nullable();
            $table->boolean('IsVerified')->default(false);
            $table->date('IssueDate')->nullable();
            $table->timestamps();

            $table->foreign('CVID')->references('CVID')->on('cv')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_certifications');
    }
};
