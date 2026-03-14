<?php

namespace App\Http\Middleware;

use App\Services\LicenseManagerClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLicense
{
    public function __construct(private LicenseManagerClient $client)
    {
    }

    /**
     * Verify that the application has a valid license.
     *
     * Register in bootstrap/app.php:
     *   ->withMiddleware(function (Middleware $middleware) {
     *       $middleware->alias([
     *           'verify-license' => \App\Http\Middleware\VerifyLicense::class,
     *       ]);
     *   })
     *
     * Usage in routes:
     *   Route::middleware('verify-license')->group(function () { ... });
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->client->isLicensed()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'A valid license is required to access this resource.',
                ], 403);
            }

            abort(403, 'A valid license is required to access this resource.');
        }

        return $next($request);
    }
}
