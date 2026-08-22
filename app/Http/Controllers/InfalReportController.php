<?php

namespace App\Http\Controllers;

use App\Exports\InfalReportExport;
use App\Support\InfalReportRow;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class InfalReportController extends Controller
{
    private array $reportRoles = ['bendahara', 'super_admin', 'kepala_sekolah'];

    public function index(Request $request)
    {
        $this->authorizeReportAccess();
        $this->validateDateFilters($request);

        $perPage = $this->resolvePerPage($request);

        $items = $this->filteredInfalQuery($request)
            ->orderBy('lr.tanggal_mulai', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        $items->through($this->decorateRow(...));

        return view('infal_report.index', compact('items'));
    }

    public function pdf(Request $request)
    {
        $this->authorizeReportAccess();
        $this->validateDateFilters($request);

        $items = $this->filteredInfalQuery($request)
            ->orderBy('lr.tanggal_mulai', 'asc')
            ->get()
            ->map($this->decorateRow(...));

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
        $this->validateDateFilters($request);

        return Excel::download(
            new InfalReportExport($request->tanggal_mulai, $request->tanggal_selesai),
            'rekap-guru-infal.xlsx'
        );
    }

    private function filteredInfalQuery(Request $request)
    {
        $query = DB::table('leave_request_infal as lri')
            ->join('leave_requests as lr', 'lr.id', '=', 'lri.leave_request_id')
            ->join('teachers as gu', 'gu.id', '=', 'lr.teacher_id')
            ->join('teachers as gi', 'gi.id', '=', 'lri.teacher_id')
            ->where('lr.status_pengajuan', 'disetujui')
            ->where('lri.status_infal', 'disetujui')
            ->select([
                'lr.id as leave_request_id',
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

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('lr.tanggal_selesai', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('lr.tanggal_mulai', '<=', $request->tanggal_selesai);
        }

        return $query;
    }

    private function decorateRow(object $row): object
    {
        $row->tanggal_label = InfalReportRow::formatTanggalLabel($row);

        return $row;
    }

    private function authorizeReportAccess(): void
    {
        if (! auth()->check() || ! in_array(auth()->user()->role, $this->reportRoles, true)) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini.');
        }
    }

    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }

    private function validateDateFilters(Request $request): void
    {
        $validator = validator($request->all(), [
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);
        $validator->after(function ($validator) use ($request): void {
            if (! $request->filled('tanggal_mulai') || ! $request->filled('tanggal_selesai') || $validator->errors()->isNotEmpty()) {
                return;
            }
            if (Carbon::parse($request->tanggal_mulai)->diffInDays(Carbon::parse($request->tanggal_selesai)) > 366) {
                $validator->errors()->add('tanggal_selesai', 'Rentang laporan maksimal 366 hari.');
            }
        });
        $validator->validate();
    }
}
