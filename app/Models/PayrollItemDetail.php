<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItemDetail extends Model
{
    protected $fillable = [
        'payroll_item_id',
        'leave_request_id',
        'tanggal_event',
        'tipe',
        'nominal',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_event' => 'date',
        'nominal' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
