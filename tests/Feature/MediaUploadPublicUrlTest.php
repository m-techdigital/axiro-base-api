<?php

namespace Tests\Feature;

use App\Services\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadPublicUrlTest extends TestCase
{
    public function test_uploaded_public_media_url_points_to_readable_public_file(): void
    {
        $uploaded = app(MediaUploadService::class)->storeMany([
            UploadedFile::fake()->image('avatar.jpg', 80, 80),
        ], 'marketplace/test-public-urls')[0];

        try {
            $this->assertStringStartsWith('/storage/', $uploaded['url']);
            $publicPath = public_path(ltrim($uploaded['url'], '/'));

            $this->assertFileExists(
                $publicPath,
                'Uploaded media URL is not readable from public/storage. Run php artisan storage:link and ensure public/storage is a symlink to storage/app/public.',
            );
        } finally {
            Storage::disk('public')->delete($uploaded['path']);
        }
    }
}
