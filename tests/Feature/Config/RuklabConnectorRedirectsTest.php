<?php

declare(strict_types=1);

use App\Models\Redirect;
use Illuminate\Support\Facades\Schema;

describe('Ruklab connector redirects', function () {
    beforeEach(function () {
        config()->set('ruklab.token', 'el-bueno');
        config()->set('ruklab.writes_enabled', true);
    });

    it('declares the site own redirects table instead of bringing a second one', function () {
        expect(config('ruklab.redirects.model'))->toBe(Redirect::class);
        expect(Schema::hasTable('ruklab_redirects'))->toBeFalse();
    });

    it('maps columns the redirects table really has', function () {
        $redirects = config('ruklab.redirects');
        $table = (new $redirects['model'])->getTable();

        foreach ([...array_values($redirects['fields']), ...array_keys($redirects['defaults'])] as $column) {
            expect(Schema::hasColumn($table, $column))
                ->toBeTrue("Las redirecciones mapean una columna que no existe: {$column}.");
        }
    });

    it('says the site is the one managing its redirects', function () {
        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->getJson('/ruklab/v1/info')
            ->assertOk()
            ->assertJsonPath('redirects', true)
            ->assertJsonPath('redirects_manager', 'site');
    });

    it('lists what the panel already holds', function () {
        Redirect::factory()->create(['source' => '/del-panel', 'destination' => '/destino']);

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->getJson('/ruklab/v1/redirects')
            ->assertOk()
            ->assertJsonPath('manager', 'site')
            ->assertJsonFragment(['from' => '/del-panel', 'to' => '/destino', 'code' => 301, 'status' => 'active']);
    });

    it('creates a redirect in the table the panel reads, the way the panel would', function () {
        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->postJson('/ruklab/v1/redirects', ['from' => '/Viejo?utm=x', 'to' => '/nuevo', 'code' => 301])
            ->assertCreated()
            ->assertJsonPath('from', '/viejo');

        $this->assertDatabaseHas('redirects', [
            'source' => '/viejo',
            'destination' => '/nuevo',
            'status_code' => 301,
            'match_type' => 'exact',
            'preserve_query' => true,
            'is_active' => true,
        ]);
    });

    it('stores a 410 without a destination', function () {
        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->postJson('/ruklab/v1/redirects', ['from' => '/retirada', 'code' => 410])
            ->assertCreated();

        $this->assertDatabaseHas('redirects', ['source' => '/retirada', 'destination' => null, 'status_code' => 410]);
    });

    it('refuses a code the table cannot hold', function () {
        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->postJson('/ruklab/v1/redirects', ['from' => '/legal', 'code' => 451])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('redirects', ['source' => '/legal']);
    });

    it('refuses a second redirect from an origin the panel already redirects', function () {
        Redirect::factory()->create(['source' => '/ya-existe', 'destination' => '/otro']);

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->postJson('/ruklab/v1/redirects', ['from' => '/ya-existe', 'to' => '/nuevo'])
            ->assertStatus(409);
    });

    it('deactivates a redirect without deleting the row', function () {
        $redirect = Redirect::factory()->create(['source' => '/a-desactivar', 'destination' => '/x']);

        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->postJson("/ruklab/v1/redirects/{$redirect->id}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('after.status', 'inactive');

        expect($redirect->fresh()->is_active)->toBeFalse();
    });

    it('is served by the site own middleware, with no second opinion from the connector', function () {
        $this->withHeader('Authorization', 'Bearer el-bueno')
            ->postJson('/ruklab/v1/redirects', ['from' => '/pagina-vieja', 'to' => '/pagina-nueva'])
            ->assertCreated();

        $this->get('/pagina-vieja')->assertRedirect('/pagina-nueva')->assertStatus(301);
    });
});
