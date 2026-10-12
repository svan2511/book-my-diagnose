<?php

namespace App\Services\Media;

use App\Contracts\MediaStorageInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Private-disk local storage. Files live outside the public webroot and
 * are served only through signed URLs (dev-prd §73).
 */
class LocalMediaStorage implements MediaStorageInterface
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'reports');
    }

    public function temporaryUrl(string $path, int $ttlMinutes = 60): string
    {
        // Signed links must open on the admin's/patient's device. APP_URL is
        // often localhost, which is unreachable from phones — so prefer the
        // current request's host (LAN IP / domain) when generating the URL.
        // Falls back to APP_URL for console/queue/test contexts.
        try {
            $root = request()->getSchemeAndHttpHost();
        } catch (\Throwable) {
            $root = (string) config('app.url');
        }
        URL::forceRootUrl($root);

        return URL::temporarySignedRoute(
            'api.v1.reports.file',
            now()->addMinutes($ttlMinutes),
            ['path' => $path]
        );
    }

    public function delete(string $path): bool
    {
        return Storage::disk('reports')->delete($path);
    }
}
