<?php

namespace App\Exports;

use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class TeacherAccountsExport implements FromView
{
    public function view(): View
    {
        $teachers = Teacher::with(['user', 'attendanceSessions'])
            ->whereHas('user', function ($query) {
                $query->whereIn('role', Teacher::DATA_GURU_ROLES);
            })
            ->orderBy('nama_lengkap')
            ->get();

        return view('admin.exports.teacher_accounts_excel', compact('teachers'));
    }
}
