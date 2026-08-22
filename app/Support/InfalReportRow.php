<?php

namespace App\Support;

use Carbon\Carbon;

class InfalReportRow
{
    public static function formatTanggalLabel(object $row): string
    {
        if (empty($row->tanggal_mulai) || empty($row->tanggal_selesai)) {
            return '-';
        }

        $mulai = Carbon::parse($row->tanggal_mulai);
        $selesai = Carbon::parse($row->tanggal_selesai);

        if ($row->is_sementara) {
            return $mulai->format('d/m/Y').' '.self::formatTime($row->jam_mulai ?? null)
                .' - '.self::formatTime($row->jam_selesai ?? null);
        }

        return $mulai->format('d/m/Y').' - '.$selesai->format('d/m/Y');
    }

    private static function formatTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '--:--';
    }
}
