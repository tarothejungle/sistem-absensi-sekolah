<?php

namespace App\Http\Controllers;

use App\Exceptions\FaceVerificationException;
use App\Models\BiometricLivenessChallenge;
use App\Models\Teacher;
use App\Models\User;
use App\Services\BiometricLivenessChallengeService;
use App\Services\FaceVerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        $user->load('teacher.faceEnrollment');

        return view('profile.edit', compact('user'));
    }

    public function photo(User $user)
    {
        $current = auth()->user();
        abort_unless($current->id === $user->id || $current->role === 'super_admin', 403);

        $path = $this->resolvePrivateFile($user->profile_photo);
        abort_unless($path, 404);

        $response = response()->file($path);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function storeFaceEnrollment(
        Request $request,
        FaceVerificationService $face,
        BiometricLivenessChallengeService $challenges,
    )
    {
        $user = $request->user();

        abort_unless(in_array($user->role, Teacher::DATA_GURU_ROLES, true) && $user->teacher, 403);

        $data = $request->validate([
            'liveness_challenge_id' => 'required|uuid',
            'liveness_frames' => 'required|array|size:'.config('biometrics.frame_count', 4),
            'liveness_frames.*' => 'required|string|max:'.config('biometrics.max_frame_characters', 1000000),
        ]);

        $currentEnrollment = $user->teacher->faceEnrollment;
        if ($currentEnrollment) {
            return $this->faceEnrollmentResponse(
                false,
                'Template wajah sudah dikunci. Hubungi super admin untuk mereset data wajah sebelum mendaftar ulang.',
                'enrollment_locked',
                422,
            );
        }

        $challenge = null;
        try {
            $challenge = $challenges->begin(
                $user,
                $user->teacher,
                $data['liveness_challenge_id'],
                BiometricLivenessChallenge::PURPOSE_ENROLLMENT,
                null,
            );
            $result = $face->enrollLiveness($data['liveness_frames'], $challenge);
            $challenges->markPassed($challenge);
        } catch (FaceVerificationException $exception) {
            if ($challenge) {
                $challenges->markFailed($challenge, $exception->reason);
            }
            return $this->faceEnrollmentResponse(
                false,
                $exception->getMessage(),
                $exception->reason,
                422,
                'liveness_frames',
            );
        } catch (RuntimeException $exception) {
            if ($challenge) {
                $challenges->markFailed($challenge, 'enrollment_failed');
            }
            return $this->faceEnrollmentResponse(
                false,
                $exception->getMessage(),
                'enrollment_failed',
                422,
                'liveness_frames',
            );
        }

        $saved = DB::transaction(function () use ($user, $result, $challenge): bool {
            $teacher = Teacher::query()->lockForUpdate()->findOrFail($user->teacher->id);
            $enrollment = $teacher->faceEnrollment()->first();

            if ($enrollment) {
                return false;
            }

            $teacher->faceEnrollment()->create([
                'face_descriptor' => $result['embedding'],
                'model_version' => $result['model_version'],
                'liveness_challenge_id' => $challenge->id,
                'recapture_count' => 0,
                'enrolled_by' => $user->id,
                'enrolled_at' => now(),
            ]);

            return true;
        });

        if (! $saved) {
            return $this->faceEnrollmentResponse(
                false,
                'Template wajah sudah dikunci. Hubungi super admin untuk mereset data wajah sebelum mendaftar ulang.',
                'enrollment_locked',
                422,
            );
        }

        return $this->faceEnrollmentResponse(
            true,
            'Wajah berhasil didaftarkan dan dikunci sebagai rujukan verifikasi biometrik.',
        );
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
            $this->deletePrivateFile($user->profile_photo);

            $data['profile_photo'] = $this->storePrivateFile(
                $request->file('profile_photo'),
                'profile_photos/'.$user->id
            );
        }

        $user->update($data);

        if ($user->hasAttendanceAccess() && $user->teacher) {
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
            $this->deletePrivateFile($user->profile_photo);

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
            'password' => ['required', 'confirmed', \App\Support\PasswordPolicy::rule()],
        ], [
            'old_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak sesuai.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->old_password, $user->password)) {
            return back()->with('error', 'Password lama tidak sesuai.');
        }

        auth()->logoutOtherDevices($request->old_password);

        $user->password = Hash::make($request->password);
        $user->remember_token = null;
        $user->save();

        return redirect()
            ->route('profile.password.form')
            ->with('success', 'Password berhasil diubah.');
    }

    private function storePrivateFile(UploadedFile $file, string $folder): string
    {
        // Ekstensi diambil dari hasil deteksi konten (bukan nama file kiriman
        // klien) lalu dipetakan lewat whitelist. Nama file kiriman klien bisa
        // membawa ekstensi apa pun sementara folder ini dilayani langsung oleh
        // web server sehingga melewati SecurityHeaders.
        $allowedExtensions = [
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
        ];

        $guessed = strtolower((string) $file->extension());
        $extension = $allowedExtensions[$guessed] ?? null;

        if ($extension === null) {
            abort(422, 'Format foto tidak didukung.');
        }

        $fileName = time().'_'.uniqid('', true).'.'.$extension;
        Storage::disk('local')->putFileAs($folder, $file, $fileName);

        return trim($folder, '/').'/'.$fileName;
    }

    private function resolvePrivateFile(?string $relativePath): ?string
    {
        if (! $relativePath || str_contains($relativePath, '..')) {
            return null;
        }

        if (str_starts_with($relativePath, 'profile_photos/') && Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        $basename = basename($relativePath);
        foreach ([
            public_path($relativePath),
            public_path('profile_photo/'.$basename),
            public_path('profile_photos/'.$basename),
            storage_path('app/public/'.$relativePath),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function deletePrivateFile(?string $relativePath): void
    {
        if (! $relativePath || str_contains($relativePath, '..')) {
            return;
        }

        if (str_starts_with($relativePath, 'profile_photos/')) {
            Storage::disk('local')->delete($relativePath);

            return;
        }

        $path = $this->resolvePrivateFile($relativePath);
        if ($path && is_file($path)) {
            unlink($path);
        }
    }

    private function faceEnrollmentResponse(
        bool $ok,
        string $message,
        ?string $code = null,
        int $status = 200,
        string $errorKey = 'liveness_frames',
    ) {
        if (request()->expectsJson()) {
            return response()->json(array_filter([
                'ok' => $ok,
                'message' => $message,
                'code' => $code,
                'biometric' => $ok ? [
                    'enrolled' => true,
                    'model_version' => \App\Models\TeacherFaceEnrollment::MODEL_VERSION,
                    'liveness_method' => config('biometrics.liveness_method'),
                ] : null,
            ], fn ($value) => $value !== null), $status);
        }

        if (! $ok) {
            return back()->withErrors([$errorKey => $message]);
        }

        return redirect()->route('profile.edit')->with('success', $message);
    }
}
