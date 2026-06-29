<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'teacher_id',
        'gaji_pokok',
        'potongan_absen',
        'tambahan_infal',
        'gaji_bersih',
        'jumlah_absen_diganti',
        'jumlah_mengganti',
        'catatan',
    ];

    protected $casts = [
        'gaji_pokok' => 'decimal:2',
        'potongan_absen' => 'decimal:2',
        'tambahan_infal' => 'decimal:2',
        'gaji_bersih' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollItemDetail::class);
    }
}
