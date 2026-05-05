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
        Schema::table('cv', function (Blueprint $table) {
            $table->string('FilePath')->nullable()->after('PersonalSummary');
            $table->boolean('IsMain')->default(false)->after('FilePath');
            $table->longText('ParsedContent')->nullable()->after('IsMain');
            $table->string('ParsingMethod')->nullable()->after('ParsedContent');
            $table->timestamp('ParsedAt')->nullable()->after('ParsingMethod');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cv', function (Blueprint $table) {
            $table->dropColumn(['FilePath', 'IsMain', 'ParsedContent', 'ParsingMethod', 'ParsedAt']);
        });
    }
};
