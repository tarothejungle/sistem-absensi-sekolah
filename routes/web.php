<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\InfalReportController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])
    ->name('password.request');

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->name('password.email');

Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset');

Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.checkin');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.checkout');

    Route::get('/leave', [LeaveRequestController::class, 'index'])->name('leave.index');
    Route::get('/leave/create', [LeaveRequestController::class, 'create'])->name('leave.create');
    Route::post('/leave', [LeaveRequestController::class, 'store'])->name('leave.store');
    Route::post('/leave/{leave}/approve', [LeaveRequestController::class, 'approve'])->name('leave.approve');
    Route::post('/leave/{leave}/reject', [LeaveRequestController::class, 'reject'])->name('leave.reject');

    Route::get('/leave/{leave}/attachment', [LeaveRequestController::class, 'showAttachment'])->name('leave.attachment.show');

    Route::get('/leave/{leave}/edit', [LeaveRequestController::class, 'edit'])->name('leave.edit');
    Route::put('/leave/{leave}', [LeaveRequestController::class, 'update'])->name('leave.update');
    Route::delete('/leave/{leave}', [LeaveRequestController::class, 'destroy'])->name('leave.destroy');

    Route::patch('/leave/{leave}/infal/approve', [LeaveRequestController::class, 'approveInfal'])->name('leave.infal.approve');
    Route::patch('/leave/{leave}/infal/reject', [LeaveRequestController::class, 'rejectInfal'])->name('leave.infal.reject');

    Route::get('/infal-report', [InfalReportController::class, 'index'])->name('infal.report.index');
    Route::get('/infal-report/pdf', [InfalReportController::class, 'pdf'])->name('infal.report.pdf');
    Route::get('/infal-report/excel', [InfalReportController::class, 'excel'])->name('infal.report.excel');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');

    Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password.form');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/teachers', [AdminController::class, 'teachers'])->name('admin.teachers');
        Route::get('/admin/teachers/create', [AdminController::class, 'createTeacher'])->name('admin.teachers.create');
        Route::post('/admin/teachers', [AdminController::class, 'storeTeacher'])->name('admin.teachers.store');
        Route::get('/admin/teachers/{teacher}/edit', [AdminController::class, 'editTeacher'])->name('admin.teachers.edit');
        Route::put('/admin/teachers/{teacher}', [AdminController::class, 'updateTeacher'])->name('admin.teachers.update');
        Route::delete('/admin/teachers/{teacher}', [AdminController::class, 'deleteTeacher'])->name('admin.teachers.delete');
        Route::get('/admin/teachers/export/excel', [AdminController::class, 'exportTeacherAccountsExcel'])->name('admin.teachers.export.excel');
        Route::get('/admin/teachers/export/pdf', [AdminController::class, 'exportTeacherAccountsPdf'])->name('admin.teachers.export.pdf');

        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/admin/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');

        Route::get('/admin/attendance-setting', [AdminController::class, 'editAttendanceSetting'])->name('admin.attendance.setting');
        Route::post('/admin/attendance-setting', [AdminController::class, 'updateAttendanceSetting'])->name('admin.attendance.setting.update');
        
        Route::get('/admin/location', [AdminController::class, 'editLocation'])->name('admin.location');
        Route::post('/admin/location', [AdminController::class, 'updateLocation'])->name('admin.location.update');

        Route::get('/admin/attendance-sessions', [AttendanceSessionController::class, 'index'])
            ->name('admin.attendance-sessions.index');

        Route::get('/admin/attendance-sessions/create', [AttendanceSessionController::class, 'create'])
            ->name('admin.attendance-sessions.create');

        Route::post('/admin/attendance-sessions', [AttendanceSessionController::class, 'store'])
            ->name('admin.attendance-sessions.store');

        Route::get('/admin/attendance-sessions/{session}/edit', [AttendanceSessionController::class, 'edit'])
            ->name('admin.attendance-sessions.edit');

        Route::put('/admin/attendance-sessions/{session}', [AttendanceSessionController::class, 'update'])
            ->name('admin.attendance-sessions.update');

        Route::delete('/admin/attendance-sessions/{session}', [AttendanceSessionController::class, 'destroy'])
            ->name('admin.attendance-sessions.destroy');
    });


    Route::middleware('role:bendahara,super_admin')->group(function () {
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::get('/payroll/settings', [PayrollController::class, 'settings'])->name('payroll.settings');
        Route::post('/payroll/settings/bulk-update', [PayrollController::class, 'bulkUpdateSettings'])->name('payroll.settings.bulk-update');
        Route::post('/payroll/settings/{teacher}', [PayrollController::class, 'updateSetting'])->name('payroll.settings.update');
        Route::get('/payroll/{period}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('/payroll/{period}/print', [PayrollController::class, 'print'])->name('payroll.print');
        Route::get('/payroll/{period}/slip/{item}', [PayrollController::class, 'slip'])->name('payroll.slip');
    });

    Route::middleware('role:kepala_sekolah,super_admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
        Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('/reports/download/pdf', [ReportController::class, 'downloadPdf'])->name('reports.download.pdf');
    });
});
