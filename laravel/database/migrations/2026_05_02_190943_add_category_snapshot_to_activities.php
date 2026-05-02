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
        Schema::table('activities', function (Blueprint $table) {
            $table->string('category_snapshot_name')->nullable()->after('category_id');
            $table->string('category_snapshot_color')->nullable()->after('category_snapshot_name');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['category_snapshot_name', 'category_snapshot_color']);
        });
    }
};
