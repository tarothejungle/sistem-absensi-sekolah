<?php

return [
    'max_gps_accuracy_meters' => (float) env('ATTENDANCE_MAX_GPS_ACCURACY_METERS', 100),
    'photo_retention_days' => (int) env('ATTENDANCE_PHOTO_RETENTION_DAYS', 30),
];
