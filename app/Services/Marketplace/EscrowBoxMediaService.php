<?php

namespace App\Services\Marketplace;

use App\Models\EscrowBox;
use App\Models\EscrowBoxMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EscrowBoxMediaService
{
    public function store(EscrowBox $box, int $customerId, string $partySide, array $files, ?int $handoverStepId = null): array
    {
        $existingCount = $box->media()->where('party_side', $partySide)->count();
        if ($existingCount + count($files) > 20) {
            throw ValidationException::withMessages(['images' => 'Mỗi bên chỉ được lưu tối đa 20 ảnh trong một box.']);
        }

        return collect($files)->map(function (UploadedFile $file) use ($box, $customerId, $partySide, $handoverStepId) {
            $checksum = hash_file('sha256', $file->getRealPath());
            $existing = $box->media()->where('checksum', $checksum)->first();
            if ($existing) return $existing;

            [$content, $mime, $width, $height] = $this->optimize($file, 1920, 82);
            [$thumb, $thumbMime] = $this->optimize($file, 480, 76);
            $directory = 'escrow-boxes/'.$box->code.'/'.$partySide;
            $name = Str::lower(Str::random(32));
            $extension = $mime === 'image/webp' ? 'webp' : 'jpg';
            $path = $directory.'/'.$name.'.'.$extension;
            $thumbPath = $directory.'/'.$name.'-thumb.'.($thumbMime === 'image/webp' ? 'webp' : 'jpg');
            Storage::disk('local')->put($path, $content);
            Storage::disk('local')->put($thumbPath, $thumb);

            return EscrowBoxMedia::query()->create([
                'escrow_box_id' => $box->id,
                'handover_step_id' => $handoverStepId,
                'party_side' => $partySide,
                'uploaded_by_customer_id' => $customerId,
                'disk' => 'local',
                'path' => $path,
                'thumbnail_path' => $thumbPath,
                'mime' => $mime,
                'size_bytes' => strlen($content),
                'width' => $width,
                'height' => $height,
                'checksum' => $checksum,
                'status' => 'ready',
            ]);
        })->values()->all();
    }

    public function stream(EscrowBoxMedia $media, bool $thumbnail = false)
    {
        $path = $thumbnail && $media->thumbnail_path ? $media->thumbnail_path : $media->path;
        abort_unless(Storage::disk($media->disk)->exists($path), 404);
        return response(Storage::disk($media->disk)->get($path), 200, [
            'Content-Type' => $media->mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function optimize(UploadedFile $file, int $maxEdge, int $quality): array
    {
        $raw = file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        if (! $info) throw ValidationException::withMessages(['images' => 'Ảnh không hợp lệ.']);
        [$width, $height] = $info;
        if (! function_exists('imagecreatefromstring')) {
            if (strlen($raw) > 2_000_000) throw ValidationException::withMessages(['images' => 'Máy chủ chưa hỗ trợ tối ưu ảnh dung lượng lớn.']);
            return [$raw, $info['mime'], $width, $height];
        }
        $source = imagecreatefromstring($raw);
        $ratio = min(1, $maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($target, null, $quality);
            $mime = 'image/webp';
        } else {
            imagejpeg($target, null, $quality);
            $mime = 'image/jpeg';
        }
        $content = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        return [$content, $mime, $targetWidth, $targetHeight];
    }
}
