<?php

namespace App\Models;

use App\Casts\EncryptedFaceDescriptor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherFaceEnrollment extends Model
{
    public const MODEL_VERSION = 'insightface-buffalo_l-v1';

    public const EMBEDDING_DIMENSIONS = 512;

    public const MAX_CAPTURE_ATTEMPTS = 3;

    protected $fillable = [
        'teacher_id',
        'liveness_challenge_id',
        'face_descriptor',
        'model_version',
        'recapture_count',
        'enrolled_by',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'face_descriptor' => EncryptedFaceDescriptor::class,
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

    public function isLocked(): bool
    {
        return $this->exists;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function livenessChallenge(): BelongsTo
    {
        return $this->belongsTo(BiometricLivenessChallenge::class, 'liveness_challenge_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
