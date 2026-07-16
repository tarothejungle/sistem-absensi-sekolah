<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use Illuminate\Http\Request;

class AttendanceSessionController extends Controller
{
    public function index()
    {
        $sessions = AttendanceSession::orderBy('jam_masuk')->get();

        return view('admin.attendance-sessions.index', compact('sessions'));
    }

    public function create()
    {
        $session = null;

        return view('admin.attendance-sessions.form', compact('session'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        AttendanceSession::create($data);

        return redirect()
            ->route('admin.attendance-sessions.index')
            ->with('success', 'Sesi absensi berhasil ditambahkan.');
    }

    public function edit(AttendanceSession $session)
    {
        return view('admin.attendance-sessions.form', compact('session'));
    }

    public function update(Request $request, AttendanceSession $session)
    {
        $data = $request->validate($this->rules());

        $session->update($data);

        return redirect()
            ->route('admin.attendance-sessions.index')
            ->with('success', 'Sesi absensi berhasil diperbarui.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'session_ids' => 'required|array|min:1',
            'session_ids.*' => 'exists:attendance_sessions,id',
        ]);

        $sessions = AttendanceSession::withCount('teachers')
            ->whereIn('id', $data['session_ids'])
            ->get();

        if ($sessions->isEmpty()) {
            return back()->with('error', 'Pilih sesi absensi yang ingin dihapus.');
        }

        $blockedSessions = $sessions->where('teachers_count', '>', 0);

        if ($blockedSessions->isNotEmpty()) {
            return back()->with('error', 'Sebagian sesi masih digunakan oleh guru, jadi tidak bisa dihapus massal.');
        }

        AttendanceSession::whereIn('id', $sessions->pluck('id'))->delete();

        return back()->with('success', 'Sesi absensi terpilih berhasil dihapus.');
    }

    public function destroy(AttendanceSession $session)
    {
        if ($session->teachers()->count() > 0) {
            return back()->with('error', 'Sesi tidak dapat dihapus karena masih digunakan oleh guru.');
        }
 
        $session->delete();
 
        return back()->with('success', 'Sesi absensi berhasil dihapus.');
    }


    public function toggle(AttendanceSession $session)
    {
        $session->update([
            'status' => $session->status === 'aktif' ? 'nonaktif' : 'aktif',
        ]);

        return back()->with('success', 'Status sesi absensi berhasil diubah.');
    }

    private function rules(): array
    {
        return [
            'nama_sesi' => 'required|string|max:100',
            'jam_masuk' => 'required|date_format:H:i',
            'jam_pulang' => 'required|date_format:H:i',
            'toleransi_terlambat' => 'required|integer|min:0',
            'batas_check_in_mulai' => 'required|date_format:H:i',
            'batas_check_in_selesai' => 'required|date_format:H:i',
            'batas_check_out_mulai' => 'required|date_format:H:i',
            'batas_check_out_selesai' => 'required|date_format:H:i',
            'status' => 'required|in:aktif,nonaktif',
        ];
    }
}
