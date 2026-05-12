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
        Schema::table('cv_certifications', function (Blueprint $table) {
            $table->string('FilePath')->nullable()->after('IssueDate');
            $table->string('CredentialID')->nullable()->after('FilePath');
            $table->string('CredentialURL')->nullable()->after('CredentialID');
            $table->json('ExtractedData')->nullable()->after('CredentialURL');
            $table->string('ExtractionMethod')->nullable()->after('ExtractedData');
            $table->timestamp('ExtractedAt')->nullable()->after('ExtractionMethod');
            $table->string('VerificationStatus')->default('pending')->after('ExtractedAt');
            $table->text('VerificationNotes')->nullable()->after('VerificationStatus');
            $table->float('AiConfidenceScore')->nullable()->after('VerificationNotes');
            $table->string('AiModel')->nullable()->after('AiConfidenceScore');
            $table->timestamp('VerifiedAt')->nullable()->after('AiModel');
            $table->unsignedInteger('VerifiedBy')->nullable()->after('VerifiedAt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cv_certifications', function (Blueprint $table) {
            $table->dropColumn([
                'FilePath',
                'CredentialID',
                'CredentialURL',
                'ExtractedData',
                'ExtractionMethod',
                'ExtractedAt',
                'VerificationStatus',
                'VerificationNotes',
                'AiConfidenceScore',
                'AiModel',
                'VerifiedAt',
                'VerifiedBy',
            ]);
        });
    }
};
