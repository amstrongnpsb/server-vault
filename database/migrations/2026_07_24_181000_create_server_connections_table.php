<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignUuid('target_server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('type');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['source_server_id', 'target_server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_connections');
    }
};
