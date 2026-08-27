<?php

declare(strict_types=1);

describe('NoIndexHealthCheck', function (): void {
    describe('handle', function (): void {
        it('should noindex the health-check page', function (): void {
            $this->get('/up')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        });

        it('should leave a real page without the noindex header', function (): void {
            $this->get('/')
                ->assertSuccessful()
                ->assertHeaderMissing('X-Robots-Tag');
        });
    });
});
