<?php

namespace App\Exports;

use App\Models\LeaveRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Support\ExcelCell;

class InfalReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
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
        $query = LeaveRequest::with(['teacher', 'infalTeacher'])
            ->where('status_pengajuan', 'disetujui')
            ->where('status_infal', 'disetujui')
            ->whereNotNull('infal_teacher_id');

        if ($this->tanggalMulai) {
            $query->whereDate('tanggal_mulai', '>=', $this->tanggalMulai);
        }

        if ($this->tanggalSelesai) {
            $query->whereDate('tanggal_selesai', '<=', $this->tanggalSelesai);
        }

        return $query->orderBy('tanggal_mulai', 'asc')->get();
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
            ExcelCell::escape($item->tanggalLabel()),
            ExcelCell::escape($item->teacher->nama_lengkap ?? '-'),
            ExcelCell::escape($item->infalTeacher->nama_lengkap ?? '-'),
            ExcelCell::escape(ucfirst($item->jenis_pengajuan)),
            ExcelCell::escape($item->alasan),
            'Disetujui',
            'Disetujui',
        ];
    }
}
