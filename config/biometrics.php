<?php

return [
    'liveness_required' => env('FACE_LIVENESS_REQUIRED', false),
    'liveness_method' => 'active_head_pose_v1',
    'challenge_ttl_seconds' => (int) env('FACE_LIVENESS_CHALLENGE_TTL_SECONDS', 90),
    'audit_retention_days' => (int) env('FACE_LIVENESS_AUDIT_RETENTION_DAYS', 90),
    'frame_count' => 4,
    'max_frame_characters' => 1_000_000,
    'status_rate_limit_per_minute' => (int) env('FACE_STATUS_RATE_LIMIT_PER_MINUTE', 60),
    'challenge_rate_limit_per_minute' => (int) env('FACE_CHALLENGE_RATE_LIMIT_PER_MINUTE', 15),
    'enrollment_rate_limit_per_minute' => (int) env('FACE_ENROLLMENT_RATE_LIMIT_PER_MINUTE', 8),
    'attendance_rate_limit_per_minute' => (int) env('FACE_ATTENDANCE_RATE_LIMIT_PER_MINUTE', 10),
];
