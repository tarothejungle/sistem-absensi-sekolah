<?php

namespace App\Http\Controllers;

use App\Models\HolidaySetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidaySettingController extends Controller
{
    public function index()
    {
        $holidays = HolidaySetting::orderByDesc('tanggal')
            ->paginate(10);

        return view('admin.holidays.index', compact('holidays'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        HolidaySetting::create($data);

        return back()->with('success', 'Hari libur berhasil ditambahkan.');
    }

    public function update(Request $request, HolidaySetting $holiday)
    {
        $data = $this->validatedData($request, $holiday);

        $holiday->update($data);

        return back()->with('success', 'Hari libur berhasil diperbarui.');
    }

    public function destroy(HolidaySetting $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Hari libur berhasil dihapus.');
    }

    public function toggle(HolidaySetting $holiday)
    {
        $holiday->update([
            'status' => $holiday->status === 'aktif' ? 'nonaktif' : 'aktif',
        ]);

        return back()->with('success', 'Status hari libur berhasil diubah.');
    }

    private function validatedData(Request $request, ?HolidaySetting $holiday = null): array
    {
        $uniqueDateRule = Rule::unique('holiday_settings', 'tanggal');

        if ($holiday) {
            $uniqueDateRule->ignore($holiday->id);
        }

        return $request->validate([
            'tanggal' => [
                'required',
                'date',
                $uniqueDateRule,
            ],
            'nama_libur' => ['required', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);
    }
}
