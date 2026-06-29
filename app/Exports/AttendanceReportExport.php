<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class AttendanceReportExport implements FromView
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function view(): View
    {
        $query = Attendance::with(['teacher.user']);

        if (!empty($this->filters['start_date'])) {
            $query->whereDate('tanggal', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->whereDate('tanggal', '<=', $this->filters['end_date']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status_kehadiran', $this->filters['status']);
        }

        $attendances = $query
            ->orderBy('tanggal', 'asc')
            ->orderBy('check_in_time', 'asc')
            ->get();

        return view('report.export_excel', compact('attendances'));
    }
}
