<?php

declare(strict_types=1);

use Toolkit\Testing\MiddleDot;

describe('MiddleDot', function () {
    describe('occurrencesIn', function () {
        it('should not appear anywhere in the app', function () {
            expect(MiddleDot::occurrencesIn(dirname(__DIR__, 2)))->toBe([]);
        });
    });
});
