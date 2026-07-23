<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetAppLocale
{
    /**
     * Supported locales in the application.
     *
     * @var array<string>
     */
    private const SUPPORTED_LOCALES = ['km', 'en'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');
        $locale = config('app.locale', 'km');

        if ($header !== null && $header !== '') {
            // Extract primary language tag (e.g., "km-KH,km;q=0.9,en;q=0.8" -> "km")
            $primaryTag = strtolower(trim(explode(',', explode(';', $header)[0])[0]));
            $languageCode = explode('-', explode('_', $primaryTag)[0])[0];

            if (in_array($languageCode, self::SUPPORTED_LOCALES, true)) {
                $locale = $languageCode;
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
