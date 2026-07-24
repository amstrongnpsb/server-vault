<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_connections', function (Blueprint $table) {
            $table->string('source_handle')->nullable()->after('target_server_id');
            $table->string('target_handle')->nullable()->after('source_handle');
        });
    }

    public function down(): void
    {
        Schema::table('server_connections', function (Blueprint $table) {
            $table->dropColumn(['source_handle', 'target_handle']);
        });
    }
};
