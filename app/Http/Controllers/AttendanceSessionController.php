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
        $request->validate([
            'nama_sesi' => 'required|string|max:100',
            'jam_masuk' => 'required',
            'jam_pulang' => 'required',
            'toleransi_terlambat' => 'required|integer|min:0',
            'batas_check_in_mulai' => 'required',
            'batas_check_in_selesai' => 'required',
            'batas_check_out_mulai' => 'required',
            'batas_check_out_selesai' => 'required',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        AttendanceSession::create($request->all());

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
        $request->validate([
            'nama_sesi' => 'required|string|max:100',
            'jam_masuk' => 'required',
            'jam_pulang' => 'required',
            'toleransi_terlambat' => 'required|integer|min:0',
            'batas_check_in_mulai' => 'required',
            'batas_check_in_selesai' => 'required',
            'batas_check_out_mulai' => 'required',
            'batas_check_out_selesai' => 'required',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        $session->update($request->all());

        return redirect()
            ->route('admin.attendance-sessions.index')
            ->with('success', 'Sesi absensi berhasil diperbarui.');
    }

    public function destroy(AttendanceSession $session)
    {
        if ($session->teachers()->count() > 0) {
            return back()->with('error', 'Sesi tidak dapat dihapus karena masih digunakan oleh guru.');
        }

        $session->delete();

        return back()->with('success', 'Sesi absensi berhasil dihapus.');
    }
}