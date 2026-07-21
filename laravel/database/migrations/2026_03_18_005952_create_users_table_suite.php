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
	    Schema::create('users', function (Blueprint $table) {
		    $table->id();
           	    // Relación con la compañía (Nullable para Nivel 1)
		    $table->foreignId('company_id')->nullable()->constrained('companies');
		    // Datos Sincronizados de C2D
		    $table->integer('c2d_user_id')->nullable()->unique();
		    $table->string('email')->unique();
		    $table->string('first_name')->nullable();
		    $table->string('last_name')->nullable();
		    $table->string('phone')->nullable();
		    $table->text('avatar')->nullable();
		    // Autenticación y Roles
		    $table->string('password')->nullable();
		    $table->string('role')->nullable(); // admin, supervisor, operator...
		    $table->integer('access_right_id')->nullable();
		    $table->text('auth_key')->nullable(); // Token dinámico para Iframe
		    // Estados y Auditoría
		    $table->string('status')->default('enabled');
		    $table->boolean('isdeleted')->default(0);
		    $table->rememberToken();
		    $table->timestamps(); // created_at y updated_at
	    });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_table_suite');
    }
};
