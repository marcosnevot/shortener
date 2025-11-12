<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbuseReport extends Model
{
    public $timestamps = false;

    protected $fillable = ['link_id','reason','details','created_at'];
    protected $casts = ['created_at'=>'datetime'];
}
