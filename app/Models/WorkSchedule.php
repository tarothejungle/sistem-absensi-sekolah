<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    protected $fillable = ['teacher_id','hari','jam_masuk','jam_pulang','toleransi_terlambat','status'];
    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
}
