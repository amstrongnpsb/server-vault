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
        Schema::create('servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('host'); // IP address or hostname
            $table->string('os'); // Operating system (allows custom names)
            $table->enum('status', ['Online', 'Offline'])->default('Offline');
            $table->text('description')->nullable();
            $table->integer('port')->default(22); // SSH port
            $table->string('username')->nullable(); // SSH username
            $table->text('credentials')->nullable(); // Encrypted credentials/keys
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'os']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};