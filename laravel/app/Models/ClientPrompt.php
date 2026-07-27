<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPrompt extends Model
{
    protected $table = 'client_prompts';

    protected $fillable = [
        'company_id', 'template_id', 'name', 'prompt_text', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
