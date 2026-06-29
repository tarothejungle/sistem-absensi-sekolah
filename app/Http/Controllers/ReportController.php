<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceReportExport;
use App\Models\Attendance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // $perPage = $this->resolvePerPage($request);
        $query = Attendance::with(['teacher.user']);

        if ($request->filled('start_date')) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('status_kehadiran', $request->status);
        }

        // $allAttendances = (clone $query)
        //     ->orderBy('tanggal', 'asc')
        //     ->orderBy('check_in_time', 'asc')
        //     ->get();

        $attendances = $query
            ->orderBy('tanggal', 'asc')
            ->orderBy('check_in_time', 'asc')
            ->get();
            // ->paginate($perPage)
            // ->withQueryString();

        $chartLabels = [
            'Hadir',
            'Terlambat',
            'Izin',
            'Sakit',
            'Cuti',
            'Alfa',
        ];

        $chartData = [
            $attendances->where('status_kehadiran', 'hadir')->count(),
            $attendances->where('status_kehadiran', 'terlambat')->count(),
            $attendances->where('status_kehadiran', 'izin')->count(),
            $attendances->where('status_kehadiran', 'sakit')->count(),
            $attendances->where('status_kehadiran', 'cuti')->count(),
            $attendances->where('status_kehadiran', 'alfa')->count(),
        ];

        return view('report.index', compact(
            'attendances',
            'chartLabels',
            'chartData'
        ));
    }

    public function exportExcel(Request $request)
    {
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
        ];

        return Excel::download(
            new AttendanceReportExport($filters),
            'laporan-absensi.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $attendances = $this->getFilteredAttendances($request);

        $pdf = Pdf::loadView('report.export_pdf', compact('attendances'))
            ->setPaper('a4', 'landscape');

        $pdfBase64 = base64_encode($pdf->output());
        $downloadUrl = route('reports.download.pdf', $request->query());

        return view('report.preview_pdf', compact('pdfBase64', 'downloadUrl'));
    }

    public function downloadPdf(Request $request)
    {
        $attendances = $this->getFilteredAttendances($request);

        $pdf = Pdf::loadView('report.export_pdf', compact('attendances'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-absensi.pdf');
    }

    private function getFilteredAttendances(Request $request)
    {
        $query = Attendance::with(['teacher.user']);

        if ($request->filled('start_date')) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('status_kehadiran', $request->status);
        }

        return $query
            ->orderBy('tanggal', 'asc')
            ->orderBy('check_in_time', 'asc')
            ->get();
    }


    // private function resolvePerPage(Request $request, int $default = 7): int
    // {
    //     $allowed = [7, 14, 21, 28, 35, 70];
    //     $perPage = (int) $request->input('per_page', $default);

    //     return in_array($perPage, $allowed, true) ? $perPage : $default;
    // }
}
