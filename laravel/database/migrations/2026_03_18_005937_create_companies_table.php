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
    	Schema::create('companies', function (Blueprint $table) {
        	$table->id(); // Autoincremental local
        	$table->integer('company_id')->unique(); // ID de Chat2Desk
        	$table->string('name');
        	$table->text('api_token')->nullable(); // Guardaremos encriptado
        	$table->string('status')->default('active');
        	$table->boolean('isdeleted')->default(0); // Borrado lógico
        	$table->timestamps(); // created_at y updated_at
    	});
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
