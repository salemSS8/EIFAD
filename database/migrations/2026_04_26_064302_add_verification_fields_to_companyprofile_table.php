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
        Schema::table('companyprofile', function (Blueprint $table) {
            $table->string('VerificationStatus')->default('Unverified')->after('IsCompanyVerified');
            $table->json('VerificationDocuments')->nullable()->after('VerificationStatus');
            $table->timestamp('VerifiedAt')->nullable()->after('VerificationDocuments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companyprofile', function (Blueprint $table) {
            $table->dropColumn(['VerificationStatus', 'VerificationDocuments', 'VerifiedAt']);
        });
    }
};
