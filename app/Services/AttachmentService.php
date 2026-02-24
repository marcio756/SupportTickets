<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Handles file uploads and attachments for the ticketing system.
 * Centralizes the upload logic to prevent code duplication across controllers.
 */
class AttachmentService
{
    /**
     * Stores the uploaded file in the designated public directory.
     *
     * @param UploadedFile|null $file The file instance to be uploaded.
     * @param string $path The directory path where the file will be stored.
     * @return string|null The stored file path or null if no file was provided.
     */
    public function store(?UploadedFile $file, string $path = 'attachments'): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store($path, 'public');
    }
}