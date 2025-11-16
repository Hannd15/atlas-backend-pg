<?php

namespace Tests\Unit\Services;

use App\Models\File;
use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Config::set('filesystems.default', 'public');
    }

    public function test_store_uploaded_files_creates_file_records_for_each_upload(): void
    {
        $service = new FileStorageService;

        $first = UploadedFile::fake()->create('plan.pdf', 120, 'application/pdf');
        $second = UploadedFile::fake()->create('annex.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $stored = $service->storeUploadedFiles([
            [$first, null],
            $second,
        ]);

        $this->assertCount(2, $stored);
        $this->assertSame(2, File::count());

        $this->assertSame('plan.pdf', $stored[0]->name);
        $this->assertSame('annex.docx', $stored[1]->name);

        $this->assertTrue(Storage::disk('public')->exists($stored[0]->path));
        $this->assertTrue(Storage::disk('public')->exists($stored[1]->path));
    }

    public function test_store_uploaded_files_falls_back_to_public_disk_when_default_missing(): void
    {
        Config::set('filesystems.default', 'custom');

        $service = new FileStorageService;

        $upload = UploadedFile::fake()->create('diagram.png', 64, 'image/png');

        $stored = $service->storeUploadedFiles([$upload]);

        $this->assertCount(1, $stored);
        $file = $stored->first();
        $this->assertSame('public', $file->disk);
        $this->assertTrue(Storage::disk('public')->exists($file->path));
    }

    public function test_store_uploaded_files_returns_empty_collection_for_empty_input(): void
    {
        $service = new FileStorageService;

        $stored = $service->storeUploadedFiles([null, []]);

        $this->assertCount(0, $stored);
        $this->assertSame(0, File::count());
    }
}
