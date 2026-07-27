<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\FaceVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        $user->load('teacher.faceEnrollment');

        return view('profile.edit', compact('user'));
    }

    public function storeFaceEnrollment(Request $request, FaceVerificationService $face)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, Teacher::DATA_GURU_ROLES, true) && $user->teacher, 403);

        $data = $request->validate([
            'face_image' => 'required|string|max:4000000',
        ]);

        $currentEnrollment = $user->teacher->faceEnrollment;
        if ($currentEnrollment && ! $currentEnrollment->canRecapture()) {
            return back()->withErrors([
                'face_image' => 'Batas maksimal 3 kali recapture telah tercapai. Hubungi super admin untuk mereset data wajah.',
            ]);
        }

        try {
            $result = $face->enroll($data['face_image']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['face_image' => $exception->getMessage()]);
        }

        $saved = DB::transaction(function () use ($user, $result): bool {
            $teacher = Teacher::query()->lockForUpdate()->findOrFail($user->teacher->id);
            $enrollment = $teacher->faceEnrollment()->first();

            if ($enrollment && ! $enrollment->canRecapture()) {
                return false;
            }

            $teacher->faceEnrollment()->updateOrCreate(
                [],
                [
                    'face_descriptor' => $result['embedding'],
                    'model_version' => $result['model_version'],
                    'recapture_count' => $enrollment ? $enrollment->recapture_count + 1 : 0,
                    'enrolled_by' => $user->id,
                    'enrolled_at' => now(),
                ]
            );

            return true;
        });

        if (! $saved) {
            return back()->withErrors([
                'face_image' => 'Batas maksimal 3 kali recapture telah tercapai. Hubungi super admin untuk mereset data wajah.',
            ]);
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', $currentEnrollment
                ? 'Data wajah berhasil di-recapture.'
                : 'Wajah berhasil didaftarkan untuk absensi.');
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:100',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'instansi_mengajar' => 'nullable|string|max:150',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'pendidikan_terakhir' => 'nullable|string|max:100',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'profile_photo.image' => 'File yang diunggah harus berupa gambar.',
            'profile_photo.mimes' => 'Foto harus berformat JPG, JPEG, atau PNG.',
            'profile_photo.max' => 'Ukuran foto maksimal 2MB.',
        ]);

        $data = [
            'name' => $request->name,
            'instansi_mengajar' => $request->instansi_mengajar,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'pendidikan_terakhir' => $request->pendidikan_terakhir,
        ];

        if ($request->hasFile('profile_photo')) {
            $this->deletePublicFile($user->profile_photo);

            $data['profile_photo'] = $this->storePublicFile(
                $request->file('profile_photo'),
                'profile_photo'
            );
        }

        $user->update($data);

        if (in_array($user->role, ['guru', 'bendahara', 'kepala_sekolah', 'tenaga_kependidikan'], true) && $user->teacher) {
            $user->teacher->update([
                'nama_lengkap' => $request->name,
            ]);
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Profil berhasil diperbarui.');
    }

    public function deletePhoto()
    {
        $user = auth()->user();

        if ($user->profile_photo) {
            $this->deletePublicFile($user->profile_photo);

            $user->profile_photo = null;
            $user->save();
        }

        return back()->with('success', 'Foto profil berhasil dihapus.');
    }

    public function password()
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ], [
            'old_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak sesuai.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->old_password, $user->password)) {
            return back()->with('error', 'Password lama tidak sesuai.');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()
            ->route('profile.password.form')
            ->with('success', 'Password berhasil diubah.');
    }

    private function storePublicFile($file, string $folder): string
    {
        $fileName = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $destinationPath = public_path($folder);

        if (! is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);

        return $folder.'/'.$fileName;
    }

    private function deletePublicFile(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (str_contains($relativePath, '..')) {
            return;
        }

        foreach ([public_path($relativePath), base_path($relativePath)] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
