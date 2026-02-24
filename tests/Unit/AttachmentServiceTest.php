<?php

namespace Tests\Unit;

use App\Services\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentServiceTest extends TestCase
{
    public function test_it_stores_uploaded_file_in_correct_directory()
    {
        Storage::fake('public');
        $service = new AttachmentService();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $path = $service->store($file, 'custom_attachments');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('custom_attachments/', $path);
    }

    public function test_it_returns_null_when_no_file_is_provided()
    {
        $service = new AttachmentService();
        
        $path = $service->store(null);

        $this->assertNull($path);
    }
}