<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();

        return view('profile.edit', compact('user'));
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
            // Hapus foto lama jika ada dan tersimpan di public/profile_photo
            if ($user->profile_photo) {
                $oldPhotoPath = base_path($user->profile_photo);

                if (file_exists($oldPhotoPath)) {
                    unlink($oldPhotoPath);
                }
            }

            $file = $request->file('profile_photo');

            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // File akan masuk ke folder: public/profile_photo
            $destinationPath = base_path('profile_photo');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $fileName);

            // Yang disimpan ke database
            $data['profile_photo'] = 'profile_photo/' . $fileName;
        }

        $user->update($data);

        if ($user->role === 'guru' && $user->teacher) {
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
            $photoPath = base_path($user->profile_photo);

            if (file_exists($photoPath)) {
                unlink($photoPath);
            }

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
            'password' => 'required|min:5|confirmed',
        ], [
            'old_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 5 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak sesuai.',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->old_password, $user->password)) {
            return back()->with('error', 'Password lama tidak sesuai.');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()
            ->route('profile.password.form')
            ->with('success', 'Password berhasil diubah.');
    }
}