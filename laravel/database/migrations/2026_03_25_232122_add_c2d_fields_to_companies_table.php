<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Identificadores remotos
            $table->unsignedBigInteger('remote_id')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('partner_id')->nullable()->after('remote_id');
            
            // Metadatos y configuración
            $table->string('company_mode')->nullable()->after('name');
            $table->string('lang', 10)->nullable()->after('company_mode');
            $table->string('timezone')->nullable()->after('lang');
            
            // Campos de uso futuro (Suscripciones y Soporte)
            $table->string('subscription_type')->nullable();
            $table->text('subscription_addons')->nullable();
            $table->string('subscription_agreement_status')->nullable();
            $table->string('notifications_phone')->nullable();
            $table->string('support_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'remote_id', 'partner_id', 'company_mode', 'lang', 'timezone',
                'subscription_type', 'subscription_addons', 'subscription_agreement_status',
                'notifications_phone', 'support_type'
            ]);
        });
    }
};
