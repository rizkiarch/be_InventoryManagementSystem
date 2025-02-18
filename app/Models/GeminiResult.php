<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeminiResult extends Model
{
    protected $fillable = [
        'prompt',
        'response',
        'raw_response',
        'status'
    ];

    protected $casts = [
        'raw_response' => 'array'
    ];

    protected $attributes = [
        'response' => null
    ];
}
