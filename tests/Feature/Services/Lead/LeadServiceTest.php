<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Mail\Lead\NewLeadMail;
use App\Models\Landing;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Lead\LeadCapturedNotification;
use App\Services\Lead\LeadService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

describe('LeadService', function () {
    describe('capture', function () {
        it('should persist a Lead with status New by default', function () {
            Mail::fake();
            Notification::fake();

            $service = app(LeadService::class);

            $lead = $service->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
                'phone' => '600000000',
                'message' => 'Necesito un generador para una boda.',
            ]);

            expect($lead)->toBeInstanceOf(Lead::class);
            expect($lead->exists)->toBeTrue();
            expect($lead->status)->toBe(LeadStatus::New);
            expect(Lead::count())->toBe(1);
        });

        it('should link a Lead to its source Landing when landing_id is provided', function () {
            Mail::fake();
            Notification::fake();

            $landing = Landing::factory()->create();
            $service = app(LeadService::class);

            $lead = $service->capture([
                'landing_id' => $landing->id,
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
                'source_url' => url('/'.$landing->slug),
            ]);

            expect($lead->landing->id)->toBe($landing->id);
            expect($lead->source_url)->toBe(url('/'.$landing->slug));
        });
    });

    describe('side effects', function () {
        it('should queue NewLeadMail when LEAD_NOTIFY_EMAIL is set', function () {
            config(['leads.notify_email' => 'admin@rental.test']);
            Mail::fake();
            Notification::fake();

            app(LeadService::class)->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
            ]);

            Mail::assertQueued(NewLeadMail::class, fn (NewLeadMail $mail): bool => $mail->hasTo('admin@rental.test')
                && $mail->lead->name === 'Cristian'
            );
        });

        it('should notify every address when LEAD_NOTIFY_EMAIL lists several', function () {
            config(['leads.notify_email' => 'hola@cliente.test, avisos@agencia.test']);
            Mail::fake();
            Notification::fake();

            app(LeadService::class)->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
            ]);

            Mail::assertQueued(NewLeadMail::class, fn (NewLeadMail $mail): bool => $mail->hasTo('hola@cliente.test')
                && $mail->hasTo('avisos@agencia.test')
            );
        });

        it('should fall back to the first registered user when LEAD_NOTIFY_EMAIL is empty', function () {
            config(['leads.notify_email' => null]);
            Mail::fake();
            Notification::fake();

            $owner = User::factory()->create(['email' => 'owner@rental.test']);
            User::factory()->create(['email' => 'second@rental.test']);

            app(LeadService::class)->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
            ]);

            Mail::assertQueued(NewLeadMail::class, fn (NewLeadMail $mail): bool => $mail->hasTo($owner->email));
        });

        it('should NOT queue email when LEAD_NOTIFY_EMAIL is empty and there are no users', function () {
            config(['leads.notify_email' => null]);
            Mail::fake();
            Notification::fake();

            app(LeadService::class)->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
            ]);

            Mail::assertNothingQueued();
        });

        it('should send the Discord notification when DISCORD_WEBHOOK_URL is set', function () {
            config(['services.discord.webhook_url' => 'https://discord.com/api/webhooks/abc']);
            Mail::fake();
            Notification::fake();

            $lead = app(LeadService::class)->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
            ]);

            Notification::assertSentTo(
                new AnonymousNotifiable,
                LeadCapturedNotification::class,
                fn (LeadCapturedNotification $notification): bool => $notification->lead->is($lead)
            );
        });

        it('should NOT send the Discord notification when DISCORD_WEBHOOK_URL is null', function () {
            config(['services.discord.webhook_url' => null]);
            Mail::fake();
            Notification::fake();

            app(LeadService::class)->capture([
                'name' => 'Cristian',
                'email' => 'cristian@example.com',
            ]);

            Notification::assertNothingSent();
        });
    });
});
