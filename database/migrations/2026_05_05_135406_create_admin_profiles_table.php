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
        Schema::create('adminprofile', function (Blueprint $table) {
            $table->integer('AdminID')->primary();
            $table->string('EmployeeID')->unique()->nullable();
            $table->string('Department')->nullable();
            $table->string('Position')->nullable();
            $table->json('Permissions')->nullable();
            $table->text('InternalNotes')->nullable();
            $table->timestamps();

            $table->foreign('AdminID')->references('UserID')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adminprofile');
    }
};
