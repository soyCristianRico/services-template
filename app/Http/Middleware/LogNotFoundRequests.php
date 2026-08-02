<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\NotFoundLog;
use App\Services\Seo\RedirectPath;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Records the addresses that answer 404, so the panel can offer them as
 * redirects waiting to be created.
 *
 * This is what lets the client keep the site tidy without anyone telling them
 * what is broken: the list of dead links is the to-do list.
 */
final class LogNotFoundRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $this->record(
                RedirectPath::normalize($request->path()),
                $this->referrer($request),
            );
        }

        return $response;
    }

    protected function shouldLog(Request $request, Response $response): bool
    {
        if (! config('redirects.log_not_found')) {
            return false;
        }

        if ($response->getStatusCode() !== 404 || ! $request->isMethodSafe()) {
            return false;
        }

        $path = $request->path();

        if (RedirectPath::isExcluded($path)) {
            return false;
        }

        return ! $this->isNoise('/'.ltrim($path, '/'));
    }

    /**
     * Bot probes and missing assets outnumber real broken links by orders of
     * magnitude. Logging them would make the screen useless — and the table huge.
     */
    protected function isNoise(string $path): bool
    {
        foreach ((array) config('redirects.ignored_not_found_patterns', []) as $pattern) {
            if (@preg_match(RedirectPath::compilePattern($pattern), $path) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function referrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        return $referrer === null ? null : mb_substr($referrer, 0, 500);
    }

    /**
     * One row per address, hit-counted. Deferred so a 404 page — which is often
     * served to a bot hammering the site — never waits on two writes.
     */
    protected function record(string $path, ?string $referrer): void
    {
        // `always()` is not optional here: Laravel skips deferred work when the
        // response is 4xx, and a 404 is the only response this middleware ever
        // has anything to record.
        defer(function () use ($path, $referrer): void {
            if ($this->touch($path, $referrer) > 0) {
                return;
            }

            try {
                NotFoundLog::create([
                    'path' => $path,
                    'hits' => 1,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'last_referrer' => $referrer,
                ]);
            } catch (QueryException) {
                // Two requests for the same dead address arrived at once and the
                // other one won the unique index. Count this hit on its row
                // instead of losing it.
                $this->touch($path, $referrer);
            }
        })->always();
    }

    /**
     * @return int Rows updated: 0 means the address has not been seen before.
     */
    protected function touch(string $path, ?string $referrer): int
    {
        return NotFoundLog::withoutTimestamps(static fn (): int => NotFoundLog::query()
            ->where('path', $path)
            ->increment('hits', 1, [
                'last_seen_at' => now(),
                'last_referrer' => $referrer,
            ]));
    }
}
