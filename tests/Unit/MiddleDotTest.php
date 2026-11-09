<?php

declare(strict_types=1);

use Toolkit\Testing\MiddleDot;

describe('MiddleDot', function (): void {
    describe('occurrencesIn', function (): void {
        it('should not appear anywhere in the app', function (): void {
            expect(MiddleDot::occurrencesIn(dirname(__DIR__, 2)))->toBe([]);
        });
    });
});
