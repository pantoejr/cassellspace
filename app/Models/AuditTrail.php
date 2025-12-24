<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    protected $fillable = [
        'entity_name',
        'entity_id',
        'action',
        'user_id',
        'changes',
        'ip_address',
    ];
}
