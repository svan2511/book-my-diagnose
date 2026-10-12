<?php

namespace App\Support;

use Illuminate\Http\Request;

class ApiRequest
{
    public static function isApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
