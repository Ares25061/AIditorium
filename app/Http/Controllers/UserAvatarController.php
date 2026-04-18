<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyAvatarRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserAvatarController extends Controller
{
    use AuthorizesRequests;

    public function upload(UploadAvatarRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        if(isset($validated['user_id'])) {
            $model = User::find($validated['user_id']);
            if ($model?->id !== $user->id) {
                $user = $model;
            }
        }
        $this->authorize('update', $user);
        // Удаляем старый аватар из File если есть
        if (!is_null($user->avatar)) {
            $oldFile = File::find($user->avatar);  // avatar хранит ID
            if (!is_null($oldFile) && Storage::disk('public')->exists($oldFile->path)) {
                Storage::disk('public')->delete($oldFile->path);
                $oldFile->delete();
            }
        }
        // Сохраняем файл в storage
        $path = $request->file('avatar')->store('avatars', 'public');
        $file = File::create([
            'path' => $path,
            'original_name' => $request->file('avatar')->getClientOriginalName(),
            'mime_type' => $request->file('avatar')->getClientMimeType(),
            'extension' => strtolower($request->file('avatar')->getClientOriginalExtension()),
            'size_bytes' => $request->file('avatar')->getSize(),
            'user_id' => $user->id,
            'type' => 'avatar',
            'is_public' => true,
        ]);
        $user->update(['avatar' => $file->id]);
        return response()->json([
            'status' => 'success',
            'message' => __('messages.uploaded', ['item' => __('messages.items.avatar')]),
            'user' => $user->load('avatarFile'),  // подгружаем связь
            'avatar_url' => $user->avatar_url,
            'file' => $file
        ]);
    }

    public function destroy(DestroyAvatarRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        if(isset($validated['user_id'])) {
            $model = User::find($validated['user_id']);
            if ($model?->id !== $user->id) {
                $user = $model;
            }
        }
        $this->authorize('update', $user);
        // Удаляем из File и storage
        if (!is_null($user->avatar)) {
            $file = File::find($user->avatar);
            if (!is_null($file) && Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
                $file->delete();
            }
        }
        $user->update(['avatar' => null]);
        return response()->json([
            'status' => 'success',
            'message' => __('messages.deleted', ['item' => __('messages.items.avatar')]),
        ]);
    }
}
