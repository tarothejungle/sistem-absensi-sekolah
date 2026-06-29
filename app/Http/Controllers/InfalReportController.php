<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Exports\InfalReportExport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class InfalReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['bendahara', 'super_admin', 'kepala_sekolah'])) {
            abort(403);
        }

        $perPage = $this->resolvePerPage($request);

        $query = LeaveRequest::with(['teacher', 'infalTeacher'])
            ->where('status_pengajuan', 'disetujui')
            ->where('status_infal', 'disetujui');

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_mulai', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal_selesai', '<=', $request->tanggal_selesai);
        }

        $items = $query
            ->orderBy('tanggal_mulai', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return view('infal_report.index', compact('items'));
    }

    public function pdf(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['bendahara', 'super_admin', 'kepala_sekolah'])) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini.');
        }

        $perPage = $this->resolvePerPage($request);

        $query = LeaveRequest::with(['teacher', 'infalTeacher'])
            ->where('status_pengajuan', 'disetujui')
            ->where('status_infal', 'disetujui');

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_mulai', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal_selesai', '<=', $request->tanggal_selesai);
        }

        $items = $query->orderBy('tanggal_mulai', 'asc')->get();

        $pdf = Pdf::loadView('infal_report.pdf', [
            'items' => $items,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rekap-guru-infal.pdf');
    }

    public function excel(Request $request)
    {
        $user = auth()->user();
    
        if (!in_array($user->role, ['bendahara', 'super_admin', 'kepala_sekolah'])) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini.');
        }
    
        return Excel::download(
            new InfalReportExport($request->tanggal_mulai, $request->tanggal_selesai),
            'rekap-guru-infal.xlsx'
        );
    }


    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}
