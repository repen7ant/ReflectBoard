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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('activities')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');

            $table->string('title');
            $table->text('description')->nullable();
            $table->text('reflection_text')->nullable();
            $table->unsignedInteger('time_spent_minutes')->nullable();

            $table->enum('status', ['backlog', 'today', 'in_process', 'on_reflection', 'done'])
                  ->default('backlog');

            $table->boolean('is_project')->default(false);
            $table->boolean('is_on_board')->default(false);
            $table->boolean('is_quick_capture')->default(false);

            $table->timestamp('deadline')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
