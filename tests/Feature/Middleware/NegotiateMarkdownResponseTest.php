<?php

declare(strict_types=1);

describe('NegotiateMarkdownResponse', function (): void {
    describe('handle', function (): void {
        it('should convert a public page to Markdown when Accept is text/markdown', function (): void {
            $response = $this->get('/', ['Accept' => 'text/markdown']);

            $response->assertOk()
                ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
                ->assertHeader('Vary', 'Accept');

            expect($response->getContent())
                ->not->toContain('<html')
                ->not->toContain('<main');
        });

        it('should leave the page as HTML when no Markdown is requested', function (): void {
            $this->get('/')
                ->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
                ->assertHeader('Vary', 'Accept');
        });

        it('should not apply to the admin group', function (): void {
            $response = $this->get('/admin', ['Accept' => 'text/markdown']);

            // Guests get redirected to login before the middleware could do
            // anything, but either way the response is never text/markdown.
            expect($response->headers->get('Content-Type'))
                ->not->toBe('text/markdown; charset=UTF-8');
        });
    });
});
