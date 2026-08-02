<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Models\Redirect;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Decides whether an incoming address has to be sent somewhere else.
 *
 * Runs on every public request, so the whole table is read once into a cached
 * array: exact matches — which are the overwhelming majority — resolve with a
 * single hash lookup and no query at all. Only prefixes and patterns are walked,
 * and there are rarely more than a handful of those.
 */
final class RedirectResolver
{
    public const CACHE_KEY = 'seo.redirects.map';

    /**
     * @var array{exact: array<string, array<string, mixed>>, prefix: array<string, array<string, mixed>>, regex: list<array<string, mixed>>}|null
     */
    protected ?array $map = null;

    public function resolve(string $path, string $query = ''): ?RedirectTarget
    {
        $path = RedirectPath::normalize($path);
        $map = $this->map();

        $rule = $map['exact'][$path] ?? null;

        if ($rule === null) {
            $rule = $this->matchPrefix($map['prefix'], $path);
        }

        if ($rule === null) {
            $rule = $this->matchRegex($map['regex'], $path);
        }

        if ($rule === null) {
            return null;
        }

        return $this->toTarget($rule, $path, $query);
    }

    /**
     * Drop the in-memory copy. Only needed by tests and by anything that changes
     * redirects without going through the model.
     */
    public function forget(): void
    {
        $this->map = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, array<string, mixed>>  $prefixes
     * @return array<string, mixed>|null
     */
    protected function matchPrefix(array $prefixes, string $path): ?array
    {
        // Already sorted longest first, so `/blog/2020` wins over `/blog` and the
        // more specific rule is the one that fires.
        foreach ($prefixes as $prefix => $rule) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $rule['remainder'] = substr($path, strlen($prefix));

                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $patterns
     * @return array<string, mixed>|null
     */
    protected function matchRegex(array $patterns, string $path): ?array
    {
        foreach ($patterns as $rule) {
            $compiled = RedirectPath::compilePattern($rule['source']);

            // A pattern that stopped compiling — a migrated row, a hand-edited
            // record — must not take the whole site down with it.
            if (@preg_match($compiled, $path) !== 1) {
                continue;
            }

            $rule['compiled'] = $compiled;

            return $rule;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function toTarget(array $rule, string $path, string $query): ?RedirectTarget
    {
        if ($rule['status_code'] === RedirectStatusCode::Gone->value) {
            return new RedirectTarget($rule['id'], RedirectStatusCode::Gone->value, null);
        }

        $destination = (string) $rule['destination'];

        if ($rule['match_type'] === RedirectMatchType::Prefix->value) {
            $destination = $this->appendRemainder($destination, (string) ($rule['remainder'] ?? ''));
        }

        if ($rule['match_type'] === RedirectMatchType::Regex->value) {
            $replaced = @preg_replace($rule['compiled'], $destination, $path);

            if (! is_string($replaced) || $replaced === '') {
                return null;
            }

            $destination = $replaced;
        }

        if ($rule['preserve_query'] && $query !== '') {
            $destination = RedirectPath::withQuery($destination, $query);
        }

        $external = RedirectPath::isExternal($destination);

        // Last line of defence against an infinite loop. Validation catches the
        // obvious `/a` → `/a`, but a pattern can produce its own input for some
        // inputs and not others, and that one only shows up in production.
        if (! $external && RedirectPath::normalize($destination) === $path) {
            return null;
        }

        return new RedirectTarget(
            $rule['id'],
            $rule['status_code'],
            $external ? $destination : url($destination),
        );
    }

    protected function appendRemainder(string $destination, string $remainder): string
    {
        if ($remainder === '') {
            return $destination;
        }

        // The leftover goes on the path, never after the query string, or
        // `/aprende?ref=x` plus `/curso` would produce `/aprende?ref=x/curso`.
        [$base, $existingQuery] = array_pad(explode('?', $destination, 2), 2, null);

        $base = rtrim((string) $base, '/').$remainder;

        return $existingQuery === null ? $base : $base.'?'.$existingQuery;
    }

    /**
     * @return array{exact: array<string, array<string, mixed>>, prefix: array<string, array<string, mixed>>, regex: list<array<string, mixed>>}
     */
    protected function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        try {
            return $this->map = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());
        } catch (Throwable) {
            // No table yet (a fresh clone before migrating) or the cache store is
            // down. A broken redirect table must not take the site with it.
            return $this->map = ['exact' => [], 'prefix' => [], 'regex' => []];
        }
    }

    /**
     * @return array{exact: array<string, array<string, mixed>>, prefix: array<string, array<string, mixed>>, regex: list<array<string, mixed>>}
     */
    protected function build(): array
    {
        $map = ['exact' => [], 'prefix' => [], 'regex' => []];

        $rules = Redirect::query()
            ->active()
            ->orderBy('id')
            ->get(['id', 'source', 'destination', 'match_type', 'status_code', 'preserve_query']);

        foreach ($rules as $rule) {
            // Plain scalars, not models: this array is serialised into the cache
            // and read back on every request.
            $row = [
                'id' => $rule->id,
                'source' => $rule->source,
                'destination' => $rule->destination,
                'match_type' => $rule->match_type->value,
                'status_code' => $rule->status_code->value,
                'preserve_query' => $rule->preserve_query,
            ];

            match ($rule->match_type) {
                RedirectMatchType::Exact => $map['exact'][$rule->source] = $row,
                RedirectMatchType::Prefix => $map['prefix'][$rule->source] = $row,
                RedirectMatchType::Regex => $map['regex'][] = $row,
            };
        }

        // Longest prefix first, so the specific rule beats the general one no
        // matter which order they were created in.
        uksort($map['prefix'], static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $map;
    }
}
