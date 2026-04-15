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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id('SettingID');
            $table->integer('UserID');
            $table->foreign('UserID')->references('UserID')->on('user')->cascadeOnDelete();
            $table->boolean('EmailNotifications')->default(true);
            $table->boolean('PushNotifications')->default(true);
            $table->boolean('JobAlerts')->default(true);
            $table->boolean('ApplicationUpdates')->default(true);
            $table->boolean('MarketingEmails')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
