<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Link extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug','id_b62','sig','url','created_by','expires_at',
        'max_clicks','clicks_count','domain_scope','is_banned'
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'domain_scope' => 'array',
        'is_banned'    => 'boolean',
    ];
}
