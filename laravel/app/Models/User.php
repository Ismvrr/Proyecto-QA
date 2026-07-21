<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'company_id', 'c2d_user_id', 'email', 'first_name', 
        'last_name', 'phone', 'avatar', 'password', 
        'role', 'access_right_id', 'auth_key', 'status', 'isdeleted',
        'last_visit' // <--- Nuevo campo agregado
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'isdeleted' => 'boolean',
        'last_visit' => 'datetime', // Parsea automáticamente a objeto Carbon
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
