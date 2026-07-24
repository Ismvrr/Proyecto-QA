<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes_request', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index(['company_id', 'fecha_creacion']);
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_request', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'fecha_creacion']);
            $table->dropColumn('company_id');
        });
    }
};
