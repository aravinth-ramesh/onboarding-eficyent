<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    /**
     * Upload a file to the configured storage disk.
     *
     * Path structure: {user_id}/{timestamp}/{original_filename}
     *
     * @return array{s3_path: string, original_filename: string, mime_type: string, file_size: int, disk: string}
     */
    public function upload(UploadedFile $file, int $userId): array
    {
        $disk = config('onboarding_uploads.disk', 's3');
        $timestamp = now()->format('YmdHis');
        $originalFilename = $file->getClientOriginalName();
        $directory = "{$userId}/{$timestamp}";
        $path = "{$directory}/{$originalFilename}";

        Storage::disk($disk)->putFileAs($directory, $file, $originalFilename);

        $url = Storage::disk($disk)->url($path);

        return [
            'url' => $url,
            's3_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'disk' => $disk,
        ];
    }

    /**
     * Upload multiple files and return their metadata.
     *
     * @param  UploadedFile[]  $files
     * @return array[]
     */
    public function uploadMultiple(array $files, int $userId): array
    {
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->upload($file, $userId);
        }

        return $results;
    }
}
