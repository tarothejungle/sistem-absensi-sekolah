<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherFaceEnrollment extends Model
{
    public const MODEL_VERSION = 'insightface-buffalo_l-v1';

    public const EMBEDDING_DIMENSIONS = 512;

    public const MAX_RECAPTURES = 3;

    protected $fillable = [
        'teacher_id',
        'face_descriptor',
        'model_version',
        'recapture_count',
        'enrolled_by',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'face_descriptor' => 'array',
            'recapture_count' => 'integer',
            'enrolled_at' => 'datetime',
        ];
    }

    public function isCompatible(): bool
    {
        return $this->model_version === self::MODEL_VERSION
            && is_array($this->face_descriptor)
            && count($this->face_descriptor) === self::EMBEDDING_DIMENSIONS;
    }

    public function remainingRecaptures(): int
    {
        return max(0, self::MAX_RECAPTURES - $this->recapture_count);
    }

    public function canRecapture(): bool
    {
        return $this->remainingRecaptures() > 0;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
