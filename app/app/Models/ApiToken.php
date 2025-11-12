<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $fillable = ['name','token_hash','scopes','last_used_at'];

    protected $casts = [
        'scopes'      => 'array',
        'last_used_at'=> 'datetime',
    ];

    public $timestamps = true;
}
