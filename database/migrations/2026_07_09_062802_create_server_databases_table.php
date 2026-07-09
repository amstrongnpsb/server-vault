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
        Schema::create('server_databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // Postgresql, Mysql, etc.
            $table->string('name')->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->text('credentials')->nullable(); // encrypted
            $table->timestamps();

            $table->index('server_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_databases');
    }
};
