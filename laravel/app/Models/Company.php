<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    // Agregamos todos los campos nuevos al fillable para permitir asignación masiva
    protected $fillable = [
        'company_id', 'name', 'api_token', 'api_token_hash', 'realtime_enabled', 'status', 'isdeleted',
        'remote_id', 'partner_id', 'company_mode', 'lang', 'timezone',
        'subscription_type', 'subscription_addons', 'subscription_agreement_status',
        'notifications_phone', 'support_type', 'last_sync_at'
    ];

    protected $casts = [
        'api_token' => 'encrypted', // Magia de Laravel: Encripta el token automáticamente al guardar
        'realtime_enabled' => 'boolean',
        'isdeleted' => 'boolean',
        'last_sync_at' => 'datetime', // Parsea automáticamente a objeto Carbon
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
