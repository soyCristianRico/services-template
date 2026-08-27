<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's stock health-check page (bootstrap/app.php's `health: '/up'`) ships
 * with no canonical and no noindex of its own, and every Laravel app serves the
 * exact same page at the exact same path — so search engines were indexing ours
 * right alongside everyone else's.
 */
final class NoIndexHealthCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('up')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
