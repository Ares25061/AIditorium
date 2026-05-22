<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;

class FileUploadService
{
    public function storeUploadedFile(
        UploadedFile $uploadedFile,
        array $attributes = [],
        string $directory = 'another',
        string $disk = 'public',
    ): File {
        $path = $uploadedFile->store($directory, $disk);

        return File::create([
            ...$attributes,
            'path' => $path,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'mime_type' => $uploadedFile->getClientMimeType(),
            'extension' => strtolower($uploadedFile->getClientOriginalExtension()),
            'size_bytes' => $uploadedFile->getSize(),
        ]);
    }
}
