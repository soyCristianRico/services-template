{{-- Cookie consent banner (GDPR / ePrivacy). Drives Google Consent Mode v2. --}}
{{-- Logic is self-contained Alpine; the consent signal is read on page load by --}}
{{-- <x-cookies.consent-mode-default /> so returning visitors are not re-prompted. --}}
@php($cookieName = config('services.cookie_consent.name'))
@php($policyUrl = config('services.cookie_consent.policy_url'))
<div
    x-data="{
        cookieName: @js($cookieName),
        visible: false,
        showSettings: false,
        analytics: false,
        marketing: false,

        init() {
            const stored = this._read();
            if (!stored) {
                this.visible = true;
            } else {
                this.analytics = !!stored.analytics;
                this.marketing = !!stored.marketing;
            }
            window.addEventListener('open-cookie-settings', () => {
                const s = this._read();
                this.analytics = s ? !!s.analytics : false;
                this.marketing = s ? !!s.marketing : false;
                this.showSettings = true;
                this.visible = true;
            });
        },

        acceptAll() { this.analytics = true; this.marketing = true; this._save(); },
        rejectAll() { this.analytics = false; this.marketing = false; this._save(); },
        savePreferences() { this._save(); },

        _save() {
            const consent = { analytics: this.analytics, marketing: this.marketing, v: 1 };
            this._write(consent);
            this._updateGtag(consent);
            this.visible = false;
            this.showSettings = false;
        },

        _updateGtag(consent) {
            if (typeof window.gtag === 'function') {
                window.gtag('consent', 'update', {
                    analytics_storage: consent.analytics ? 'granted' : 'denied',
                    ad_storage: consent.marketing ? 'granted' : 'denied',
                    ad_user_data: consent.marketing ? 'granted' : 'denied',
                    ad_personalization: consent.marketing ? 'granted' : 'denied',
                });
            }
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'cookie_consent_update', cookie_consent: consent });
        },

        _read() {
            try {
                const m = document.cookie.match(new RegExp('(?:^|;\\s*)' + this.cookieName + '=([^;]+)'));
                return m ? JSON.parse(decodeURIComponent(m[1])) : null;
            } catch (e) { return null; }
        },

        _write(consent) {
            const oneYear = 60 * 60 * 24 * 365;
            document.cookie = this.cookieName + '=' + encodeURIComponent(JSON.stringify(consent)) +
                '; path=/; max-age=' + oneYear + '; SameSite=Lax';
        },
    }"
    x-show="visible"
    x-cloak
    x-transition.opacity.duration.300ms
    class="fixed inset-x-0 bottom-0 z-50 px-4 pb-4 sm:px-6 sm:pb-6"
    role="dialog"
    aria-modal="false"
    aria-label="Consentimiento de cookies"
>
    <div class="mx-auto max-w-3xl rounded-2xl bg-white p-5 shadow-2xl ring-1 ring-black/10 sm:p-6">
        {{-- Main view --}}
        <div x-show="!showSettings">
            <flux:heading size="base">Usamos cookies 🍪</flux:heading>
            <flux:text class="mt-2">
                Las utilizamos para que la web funcione correctamente, gestionar tus consultas
                y entender cómo usas la web para mejorarla. Puedes aceptarlas, rechazarlas o configurarlas.@if ($policyUrl) Más información
                en nuestra <flux:link href="{{ $policyUrl }}">política de cookies</flux:link>.@endif
            </flux:text>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" size="sm" x-on:click="showSettings = true">Configurar</flux:button>
                <flux:button variant="outline" size="sm" x-on:click="rejectAll()">Rechazar todo</flux:button>
                <flux:button variant="primary" size="sm" x-on:click="acceptAll()">Aceptar todo</flux:button>
            </div>
        </div>

        {{-- Settings view --}}
        <div x-show="showSettings" x-cloak>
            <flux:heading size="base">Configurar cookies</flux:heading>
            <div class="mt-4 space-y-3">
                <flux:switch checked disabled label="Necesarias" description="Imprescindibles para que la web funcione y poder enviar tus consultas. Siempre activas." />
                <flux:separator variant="subtle" />
                <flux:switch x-model="analytics" label="Analítica" description="Nos ayudan a entender cómo se usa la web para mejorar la experiencia." />
                <flux:separator variant="subtle" />
                <flux:switch x-model="marketing" label="Marketing" description="Nos permiten medir nuestras campañas y mostrarte ofertas relevantes." />
            </div>
            <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" size="sm" x-on:click="showSettings = false">Volver</flux:button>
                <flux:button variant="outline" size="sm" x-on:click="savePreferences()">Guardar preferencias</flux:button>
                <flux:button variant="primary" size="sm" x-on:click="acceptAll()">Aceptar todo</flux:button>
            </div>
        </div>
    </div>
</div>
