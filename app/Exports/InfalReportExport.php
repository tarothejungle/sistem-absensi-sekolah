<?php

namespace App\Exports;

use App\Support\ExcelCell;
use App\Support\InfalReportRow;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InfalReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $tanggalMulai;

    protected $tanggalSelesai;

    private int $rowNumber = 0;

    public function __construct($tanggalMulai = null, $tanggalSelesai = null)
    {
        $this->tanggalMulai = $tanggalMulai;
        $this->tanggalSelesai = $tanggalSelesai;
    }

    public function collection()
    {
        $query = DB::table('leave_request_infal as lri')
            ->join('leave_requests as lr', 'lr.id', '=', 'lri.leave_request_id')
            ->join('teachers as gu', 'gu.id', '=', 'lr.teacher_id')
            ->join('teachers as gi', 'gi.id', '=', 'lri.teacher_id')
            ->where('lr.status_pengajuan', 'disetujui')
            ->where('lri.status_infal', 'disetujui')
            ->select([
                'lr.tanggal_mulai',
                'lr.tanggal_selesai',
                'lr.jam_mulai',
                'lr.jam_selesai',
                'lr.is_sementara',
                'lr.jenis_pengajuan',
                'lr.alasan',
                'gu.nama_lengkap as guru_utama',
                'gi.nama_lengkap as guru_infal',
            ]);

        if ($this->tanggalMulai) {
            $query->whereDate('lr.tanggal_selesai', '>=', $this->tanggalMulai);
        }

        if ($this->tanggalSelesai) {
            $query->whereDate('lr.tanggal_mulai', '<=', $this->tanggalSelesai);
        }

        return $query->orderBy('lr.tanggal_mulai', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Guru Utama',
            'Guru Infal/Pengganti',
            'Jenis',
            'Alasan',
            'Status Izin',
            'Status Infal',
        ];
    }

    public function map($item): array
    {
        return [
            ++$this->rowNumber,
            ExcelCell::escape(InfalReportRow::formatTanggalLabel($item)),
            ExcelCell::escape($item->guru_utama ?? '-'),
            ExcelCell::escape($item->guru_infal ?? '-'),
            ExcelCell::escape(ucfirst($item->jenis_pengajuan)),
            ExcelCell::escape($item->alasan),
            'Disetujui',
            'Disetujui',
        ];
    }
}
