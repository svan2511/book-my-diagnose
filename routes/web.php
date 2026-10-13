<?php

use Illuminate\Support\Facades\Route;

// Merged deploy: the React SPA is built into public/app (VITE_BASE=/app/).
// Every non-API page serves it; React Router takes over client-side.
// API (/api/*), Sanctum, storage files and the /up health check are NOT
// matched here, so API 404s keep their JSON envelope.
$spa = function () {
    $index = public_path('app/index.html');

    if (! file_exists($index)) {
        abort(404, 'Frontend not built. Run the web-app build first.');
    }

    return response()->file($index);
};

Route::get('/', $spa);
Route::get('/{any}', $spa)->where('any', '^(?!api|sanctum|storage|up)(.*)$');
