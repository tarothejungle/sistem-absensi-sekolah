<?php

namespace App\Support;

class ExcelCell
{
    public static function escape(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if ($value === '') {
            return $value;
        }

        return preg_match('/^[=\-+@\t\r]/', $value) ? "'" . $value : $value;
    }
}
