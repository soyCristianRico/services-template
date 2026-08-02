<?php

declare(strict_types=1);

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Livewire\Forms\Seo\RedirectForm;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Livewire\RedirectFormTestComponent;

uses(RefreshDatabase::class);

/**
 * A form filled in but not saved, which is the state the tester on the edit
 * screen runs against.
 *
 * @param  array<string, string>  $attributes
 */
function formUnderTest(array $attributes): RedirectForm
{
    $form = Livewire::test(RedirectFormTestComponent::class)->instance()->form;

    foreach ($attributes as $property => $value) {
        $form->{$property} = $value;
    }

    return $form;
}

describe('RedirectForm', function (): void {
    describe('setRedirect', function (): void {
        it('should fill the form from an existing redirect', function (): void {
            $redirect = Redirect::factory()->prefix()->create([
                'source' => '/formacion',
                'destination' => '/legal',
                'status_code' => RedirectStatusCode::Found->value,
                'preserve_query' => false,
                'notes' => 'Migración de 2019.',
            ]);

            Livewire::test(RedirectFormTestComponent::class, ['redirect' => $redirect])
                ->assertSet('form.id', $redirect->id)
                ->assertSet('form.source', '/formacion')
                ->assertSet('form.destination', '/legal')
                ->assertSet('form.match_type', 'prefix')
                ->assertSet('form.status_code', 302)
                ->assertSet('form.preserve_query', false)
                ->assertSet('form.notes', 'Migración de 2019.');
        });
    });

    describe('save', function (): void {
        it('should store a redirect from what was typed', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/curso-antiguo')
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasNoErrors();

            expect(Redirect::query()->where('source', '/curso-antiguo')->exists())->toBeTrue();
        });

        it('should tidy up a pasted full URL into the address it will compare', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', 'https://example.test/Curso-Antiguo/')
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasNoErrors()
                ->assertSet('form.source', '/curso-antiguo');
        });

        it('should not leave a destination behind on a retired page', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/seccion-retirada')
                ->set('form.destination', '/algo')
                ->set('form.status_code', RedirectStatusCode::Gone->value)
                ->call('save')
                ->assertHasNoErrors();

            expect(Redirect::query()->first()->destination)->toBeNull();
        });

        it('should update the redirect it was opened with instead of creating another', function (): void {
            $redirect = Redirect::factory()->create(['source' => '/curso-antiguo', 'destination' => '/a']);

            Livewire::test(RedirectFormTestComponent::class, ['redirect' => $redirect])
                ->set('form.destination', '/b')
                ->call('save')
                ->assertHasNoErrors();

            expect(Redirect::query()->count())->toBe(1)
                ->and($redirect->fresh()->destination)->toBe('/b');
        });
    });

    describe('validation', function (): void {
        it('should require the old address', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasErrors(['form.source' => 'required']);
        });

        it('should require a destination unless the page was retired', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/curso-antiguo')
                ->call('save')
                ->assertHasErrors('form.destination');
        });

        it('should refuse a second redirect for an address already covered', function (): void {
            Redirect::factory()->create(['source' => '/curso-antiguo']);

            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/Curso-Antiguo/')
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasErrors(['form.source' => 'unique']);
        });

        it('should refuse a redirect that points at itself', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/cursos')
                ->set('form.destination', '/cursos/')
                ->call('save')
                ->assertHasErrors('form.destination');
        });

        it('should refuse to chain onto another redirect', function (): void {
            Redirect::factory()->create(['source' => '/paso-2', 'destination' => '/paso-3']);

            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/paso-1')
                ->set('form.destination', '/paso-2')
                ->call('save')
                ->assertHasErrors('form.destination');
        });

        it('should refuse a pattern that does not compile', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.match_type', RedirectMatchType::Regex->value)
                ->set('form.source', '^/blog/(unclosed')
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasErrors('form.source');
        });

        it('should refuse to redirect the admin', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/admin/redirects')
                ->set('form.destination', '/')
                ->call('save')
                ->assertHasErrors('form.source');
        });

        it('should refuse a prefix rule covering the whole site', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.match_type', RedirectMatchType::Prefix->value)
                ->set('form.source', '/')
                ->set('form.destination', '/blog')
                ->call('save')
                ->assertHasErrors('form.source');
        });

        it('should refuse a destination that is neither a path nor a full address', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/curso-antiguo')
                ->set('form.destination', 'oposiciones')
                ->call('save')
                ->assertHasErrors('form.destination');
        });

        it('should accept a destination on another site', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.source', '/webinar')
                ->set('form.destination', 'https://zoom.us/j/123')
                ->call('save')
                ->assertHasNoErrors();
        });

        it('should let a pattern destination keep its placeholders', function (): void {
            Livewire::test(RedirectFormTestComponent::class)
                ->set('form.match_type', RedirectMatchType::Regex->value)
                ->set('form.source', '^/blog/\d{4}/(.+)$')
                ->set('form.destination', '/blog/$1')
                ->call('save')
                ->assertHasNoErrors();
        });
    });

    describe('preview', function (): void {
        it('should show where an exact rule would send an address', function (): void {
            $form = formUnderTest(['source' => '/curso-antiguo', 'destination' => '/blog']);

            expect($form->preview('/curso-antiguo/'))->toBe('/blog')
                ->and($form->preview('/otra-cosa'))->toBeNull();
        });

        it('should show a pattern with its captures already substituted', function (): void {
            $form = formUnderTest([
                'match_type' => RedirectMatchType::Regex->value,
                'source' => '^/blog/\d{4}/(.+)$',
                'destination' => '/blog/$1',
            ]);

            expect($form->preview('/blog/2019/como-estudiar'))->toBe('/blog/como-estudiar');
        });

        it('should show what a prefix rule does with the leftover', function (): void {
            $form = formUnderTest([
                'match_type' => RedirectMatchType::Prefix->value,
                'source' => '/formacion',
                'destination' => '/legal',
            ]);

            expect($form->preview('/formacion/curso-a'))->toBe('/legal/curso-a')
                ->and($form->preview('/formacion-continua'))->toBeNull();
        });

        it('should show nothing for a pattern that does not compile', function (): void {
            $form = formUnderTest([
                'match_type' => RedirectMatchType::Regex->value,
                'source' => '^/blog/(unclosed',
                'destination' => '/blog',
            ]);

            expect($form->preview('/blog/x'))->toBeNull();
        });
    });
});
