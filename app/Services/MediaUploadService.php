<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    /** @return array<int, array{url:string,path:string,name:string,size:int,mime:string}> */
    public function storeMany(array $files, string $directory = 'marketplace/products'): array
    {
        return collect($files)->map(function (UploadedFile $file) use ($directory): array {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $filename = now()->format('YmdHis').'-'.Str::lower(Str::random(16)).'.'.$extension;
            $path = $file->storeAs($directory, $filename, 'public');

            return [
                'url' => Storage::disk('public')->url($path),
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => (int) $file->getSize(),
                'mime' => (string) $file->getMimeType(),
            ];
        })->values()->all();
    }
}
