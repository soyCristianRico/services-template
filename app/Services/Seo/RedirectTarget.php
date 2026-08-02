<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * A matched redirect, resolved down to what the response needs.
 */
final readonly class RedirectTarget
{
    public function __construct(
        public int $redirectId,
        public int $statusCode,
        /** Absolute URL to send the visitor to, or null for a 410. */
        public ?string $url,
    ) {}

    public function isGone(): bool
    {
        return $this->url === null;
    }
}
