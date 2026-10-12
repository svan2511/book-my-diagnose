<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Storage abstraction for sensitive files (dev-prd §28).
 * Local driver today; swap to Cloudinary/S3 via .env without
 * touching controllers or services.
 */
interface MediaStorageInterface
{
    /** Store an upload and return the stored path reference. */
    public function store(UploadedFile $file, string $directory): string;

    /** Build a temporary, signed access URL for a stored path. */
    public function temporaryUrl(string $path, int $ttlMinutes = 60): string;

    public function delete(string $path): bool;
}
