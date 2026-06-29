<?php

namespace App\Exports;

use App\Models\LeaveRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InfalReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $tanggalMulai;
    protected $tanggalSelesai;

    public function __construct($tanggalMulai = null, $tanggalSelesai = null)
    {
        $this->tanggalMulai = $tanggalMulai;
        $this->tanggalSelesai = $tanggalSelesai;
    }

    public function collection()
    {
        $query = LeaveRequest::with(['teacher', 'infalTeacher'])
            ->where('status_pengajuan', 'disetujui')
            ->where('status_infal', 'disetujui');

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
            'Status Izin',
            'Status Infal',
        ];
    }

    public function map($item): array
    {
        return [
            $item->tanggal_mulai->format('d/m/Y') . ' - ' . $item->tanggal_selesai->format('d/m/Y'),
            $item->teacher->nama_lengkap ?? '-',
            $item->infalTeacher->nama_lengkap ?? '-',
            ucfirst($item->jenis_pengajuan),
            $item->alasan,
            'Disetujui',
            'Disetujui',
        ];
    }
}