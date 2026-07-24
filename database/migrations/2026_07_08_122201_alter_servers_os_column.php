<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change os column from enum to string
        DB::statement('ALTER TABLE servers MODIFY COLUMN os VARCHAR(255) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to enum (only if data fits the enum values)
        DB::statement("ALTER TABLE servers MODIFY COLUMN os ENUM('Ubuntu', 'Debian', 'CentOS', 'Windows', 'Other') NOT NULL");
    }
};
