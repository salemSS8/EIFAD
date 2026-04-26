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
        Schema::create('jobdemandsnapshot', function (Blueprint $table) {
            $table->integer('SnapshotID')->autoIncrement();
            $table->string('JobTitle');
            $table->decimal('AverageSalary', 15, 2)->nullable();
            $table->integer('PostCount')->default(0);
            $table->date('SnapshotDate');

            // Indexing for performance
            $table->index(['SnapshotDate', 'PostCount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobdemandsnapshot');
    }
};
