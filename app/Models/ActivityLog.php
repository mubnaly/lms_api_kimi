<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'tenant_id', 'method', 'path', 'ip', 'user_agent', 'status',
    ];

    public $timestamps = true;
}
