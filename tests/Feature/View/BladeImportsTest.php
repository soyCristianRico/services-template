<?php

declare(strict_types=1);

use Toolkit\Testing\BladeImports;

describe('BladeImports', function (): void {
    describe('missingIn', function (): void {
        it('should import every class a view names by its short name', function (): void {
            expect(BladeImports::missingIn(resource_path('views')))->toBe([]);
        });
    });
});
