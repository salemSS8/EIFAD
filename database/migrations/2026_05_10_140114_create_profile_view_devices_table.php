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
        Schema::create('profile_view_devices', function (Blueprint $table) {
            $table->id();
            $table->integer('viewed_user_id');
            $table->string('device_hash', 64);
            $table->integer('viewer_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['viewed_user_id', 'device_hash']);
            $table->index('viewed_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_view_devices');
    }
};
