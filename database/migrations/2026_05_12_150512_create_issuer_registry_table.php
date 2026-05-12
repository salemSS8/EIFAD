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
        Schema::create('issuer_registry', function (Blueprint $table) {
            $table->id('IssuerID');
            $table->string('IssuerName');
            $table->string('IssuerDomain')->nullable();
            $table->string('VerificationMethod')->default('manual');
            $table->boolean('IsVerifiable')->default(false);
            $table->boolean('RequiresHumanReview')->default(true);
            $table->string('CredentialPattern')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuer_registry');
    }
};
