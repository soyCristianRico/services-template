<?php

declare(strict_types=1);

use App\Channels\DiscordChannel;
use App\Models\Category;
use App\Models\Landing;
use App\Models\Lead;
use App\Models\Location;
use App\Notifications\Lead\LeadCapturedNotification;

describe('LeadCapturedNotification', function () {
    describe('via', function () {
        it('should route through the Discord channel', function () {
            $notification = new LeadCapturedNotification(Lead::factory()->create());

            expect($notification->via(new stdClass))->toBe([DiscordChannel::class]);
        });
    });

    describe('toDiscord', function () {
        it('should build an embed with the lead contact fields', function () {
            $lead = Lead::factory()->create([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
                'phone' => '600000000',
                'message' => 'Necesito un generador para una boda.',
            ]);

            $payload = (new LeadCapturedNotification($lead))->toDiscord(new stdClass)->toArray();
            $embed = $payload['embeds'][0];

            expect($embed['title'])->toBe('Nuevo lead: Cristian');
            expect($embed['url'])->toBe(route('admin.leads.show', $lead));
            expect(collect($embed['fields'])->firstWhere('name', 'Email')['value'])->toBe('cristian@example.com');
            expect(collect($embed['fields'])->firstWhere('name', 'Teléfono')['value'])->toBe('600000000');
            expect(collect($embed['fields'])->firstWhere('name', 'Mensaje')['value'])->toBe('Necesito un generador para una boda.');
        });

        it('should label the origin with the landing category and location', function () {
            $category = Category::factory()->create(['name' => 'Alquiler de generadores']);
            $location = Location::factory()->create(['name' => 'Madrid']);
            $landing = Landing::factory()->forCategory($category)->inLocation($location)->create();
            $lead = Lead::factory()->fromLanding($landing)->create();

            $payload = (new LeadCapturedNotification($lead))->toDiscord(new stdClass)->toArray();
            $origin = collect($payload['embeds'][0]['fields'])->firstWhere('name', 'Origen')['value'];

            expect($origin)->toBe('Alquiler de generadores · Madrid');
        });

        it('should label the origin as direct contact when there is no landing', function () {
            $lead = Lead::factory()->create(['landing_id' => null]);

            $payload = (new LeadCapturedNotification($lead))->toDiscord(new stdClass)->toArray();
            $origin = collect($payload['embeds'][0]['fields'])->firstWhere('name', 'Origen')['value'];

            expect($origin)->toBe('Contacto directo');
        });

        it('should omit the message field when the lead has no message', function () {
            $lead = Lead::factory()->create(['message' => null]);

            $payload = (new LeadCapturedNotification($lead))->toDiscord(new stdClass)->toArray();
            $fieldNames = collect($payload['embeds'][0]['fields'])->pluck('name');

            expect($fieldNames)->not->toContain('Mensaje');
        });
    });
});
