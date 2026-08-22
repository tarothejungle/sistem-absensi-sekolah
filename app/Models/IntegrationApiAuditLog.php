<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationApiAuditLog extends Model
{
    protected $fillable = [
        'integration',
        'request_id',
        'method',
        'path',
        'ip_address',
        'key_version',
        'response_status',
        'duration_ms',
    ];
}
