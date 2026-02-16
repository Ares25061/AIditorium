<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFileRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use function PHPUnit\Framework\isEmpty;

class FileController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $files = File::paginate($request->per_page, page:$request->page);
        if ($files->count() === 0) {
            return response()->json(['error' => 'Files not found'], 404);
        }
        return response()->json(['files' => $files], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateFileRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $path = $validated['file']->store($validated['type'] ?? 'another', 'public');
        $file = File::create([
            'path' => $path,
            'user_id' => $user->id,
            'type' => $validated['type'] ?? 'another',
            'course_id' => $validated['course_id'] ?? null,
            'task_id' => $validated['task_id'] ?? null,
        ]);
        return response()->json(['message' => 'File created!', 'file' => $file], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $file = File::find($id);
        if (is_null($file)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        return response()->json(['file' => $file]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFileRequest $request, int $id)
    {
        $file = File::find($id);
        if (is_null($file)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        $validated = $request->validated();
        if (!empty($validated['type'])) {
            Storage::move($file->path, $validated['type'] . '/' . basename($file->path));
            $file->path = $validated['type'] . '/' . basename($file->path);

        }
        $file->update([...$validated]);
        return response()->json(['message' => 'File updated!', 'file' => $file], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $file = File::find($id);
        if (is_null($file)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        if(Storage::exists($file->path)) {
            Storage::delete($file->path);
        }
        $file->delete();
        return response()->json(['message' => 'File deleted']);
    }

    public function download(int $id)
    {
        // В будущем сделаю
    }
}
