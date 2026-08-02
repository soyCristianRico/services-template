<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use App\Services\Seo\RedirectPath;
use App\Services\Seo\RedirectResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Sends old addresses to their new home.
 *
 * Registered globally, ahead of routing, on purpose: many retired addresses still
 * resolve against the catch-all `/{slug}` route, so a redirect table wired to the
 * 404 handler would never see them.
 */
final class RedirectRequests
{
    public function __construct(protected RedirectResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        // A redirected POST loses its body on most clients. Redirects exist for
        // addresses people follow, and those are always GET.
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        if (RedirectPath::isExcluded($request->path())) {
            return $next($request);
        }

        $target = $this->resolver->resolve($request->path(), (string) $request->getQueryString());

        if ($target === null) {
            return $next($request);
        }

        $this->recordHit($target->redirectId);

        if ($target->isGone()) {
            abort(RedirectStatusCode::Gone->value);
        }

        return redirect()->away($target->url, $target->statusCode);
    }

    /**
     * Counted after the response is on its way, so the visitor never waits on it.
     *
     * Written straight through the query builder rather than the model: a model
     * save would fire the `saved` hook and flush the redirect cache on every
     * single visit, turning the cache into a slower version of no cache at all.
     */
    protected function recordHit(int $redirectId): void
    {
        // `always()` because a 410 is a 4xx, and Laravel drops deferred work on
        // those by default — the retired pages would be the only ones never
        // counted, which is exactly the number worth watching.
        defer(static function () use ($redirectId): void {
            Redirect::withoutTimestamps(static function () use ($redirectId): void {
                Redirect::query()
                    ->whereKey($redirectId)
                    ->increment('hits', 1, ['last_hit_at' => now()]);
            });
        })->always();
    }
}
