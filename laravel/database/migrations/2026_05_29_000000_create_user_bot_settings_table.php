<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bot_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->tinyInteger('deadline_lead_hours')->unsigned()->default(24);
            $table->string('reminder_time', 5)->nullable();
            $table->string('today_reminder_time', 5)->nullable();
            $table->smallInteger('tz_offset_minutes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bot_settings');
    }
};
