<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Handles file uploads and attachments for the ticketing system.
 * Centralizes the upload logic to prevent code duplication across controllers.
 * Architect Note: Removed hardcoded local disk dependencies to allow horizontal
 * scaling across multiple servers using Cloud Storage (S3, MinIO, etc).
 */
class AttachmentService
{
    /**
     * Stores the uploaded file in the designated directory.
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

        // Architect Note: Remover o 'public' hardcoded é obrigatório para escalar.
        // Ao usar config('filesystems.default'), o site continua a funcionar perfeitamente em 
        // local (pois o default é 'public' ou 'local'), mas permite que, no futuro, alteres apenas 
        // uma linha no ficheiro .env (FILESYSTEM_DISK=s3) para que os ficheiros comecem a ir para o 
        // Amazon S3 ou Cloudflare R2 de forma automática, aliviando o disco do teu servidor principal.
        $disk = config('filesystems.default', 'public');

        return $file->store($path, $disk);
    }
    
    /**
     * Process multiple attachments associated with a specific model.
     * Architect Note: Adicionado para garantir compatibilidade estrutural com o TicketService.
     * * @param array|UploadedFile[] $attachments
     * @param mixed $model
     * @return void
     */
    public function processAttachments($attachments, $model): void
    {
        if (!is_iterable($attachments)) {
            return;
        }

        foreach ($attachments as $attachment) {
            if ($attachment instanceof UploadedFile) {
                $path = $this->store($attachment);
                
                // Assumindo uma relação polimórfica padrão ou armazenamento na BD
                if ($path && method_exists($model, 'attachments')) {
                    $model->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $attachment->getClientOriginalName(),
                        'mime_type' => $attachment->getClientMimeType(),
                        'size' => $attachment->getSize(),
                    ]);
                }
            }
        }
    }
}