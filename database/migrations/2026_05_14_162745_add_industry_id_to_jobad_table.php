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
            $table->unsignedBigInteger('industry_id')->nullable()->after('CompanyID');
            $table->foreign('industry_id')->references('id')->on('industries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobad', function (Blueprint $table) {
            $table->dropForeign(['industry_id']);
            $table->dropColumn('industry_id');
        });
    }
};
