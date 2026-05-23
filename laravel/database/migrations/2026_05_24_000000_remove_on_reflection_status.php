<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE activities SET status = 'in_process' WHERE status = 'on_reflection'");

        DB::statement("ALTER TABLE activities MODIFY COLUMN status ENUM('backlog','today','in_process','done') NOT NULL DEFAULT 'backlog'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE activities MODIFY COLUMN status ENUM('backlog','today','in_process','on_reflection','done') NOT NULL DEFAULT 'backlog'");
    }
};
