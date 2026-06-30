<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Exports\InfalReportExport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class InfalReportController extends Controller
{
    private array $reportRoles = ['bendahara', 'super_admin', 'kepala_sekolah'];

    public function index(Request $request)
    {
        $this->authorizeReportAccess();

        $perPage = $this->resolvePerPage($request);

        $items = $this->filteredInfalQuery($request)
            ->orderBy('tanggal_mulai', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return view('infal_report.index', compact('items'));
    }

    public function pdf(Request $request)
    {
        $this->authorizeReportAccess();

        $items = $this->filteredInfalQuery($request)
            ->orderBy('tanggal_mulai', 'asc')
            ->get();

        $pdf = Pdf::loadView('infal_report.pdf', [
            'items' => $items,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rekap-guru-infal.pdf');
    }

    public function excel(Request $request)
    {
        $this->authorizeReportAccess();

        return Excel::download(
            new InfalReportExport($request->tanggal_mulai, $request->tanggal_selesai),
            'rekap-guru-infal.xlsx'
        );
    }

    private function filteredInfalQuery(Request $request)
    {
        $query = LeaveRequest::with(['teacher', 'infalTeacher'])
            ->where('status_pengajuan', 'disetujui')
            ->where('status_infal', 'disetujui')
            ->whereNotNull('infal_teacher_id');

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_mulai', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal_selesai', '<=', $request->tanggal_selesai);
        }

        return $query;
    }

    private function authorizeReportAccess(): void
    {
        if (!auth()->check() || !in_array(auth()->user()->role, $this->reportRoles, true)) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini.');
        }
    }


    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}
