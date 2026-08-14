<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Enums\LeadChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * De dónde venía quien acabó dejando sus datos.
 *
 * Hacen falta las tres señales: las UTMs no aparecen en Google Ads, que
 * etiqueta sólo con `gclid`, y el referrer no cubre ni lo directo ni lo que
 * llega por WhatsApp.
 *
 * Se lee en la PRIMERA página, no al enviar: los formularios son Livewire y en
 * ese POST el referrer es la propia web. Va en la sesión —cookie necesaria, no
 * entra en el banner— y eso se paga: quien vuelve a los tres días cuenta como
 * visita nueva.
 */
final class LeadAttribution
{
    public const SESSION_KEY = 'lead-attribution';

    /**
     * Parámetros de clic de las plataformas de pago.
     *
     * Existen porque el auto-tagging NO pone UTMs: una campaña de Google Ads
     * llega sin `utm_medium=cpc` y sin ellos se contaría como directo.
     *
     * @var list<string>
     */
    protected const CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid'];

    /** @var list<string> */
    protected const PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'paidsearch', 'paid-search', 'paid_search', 'paid_social', 'paidsocial', 'cpm', 'display', 'retargeting', 'remarketing'];

    /** @var list<string> */
    protected const EMAIL_MEDIUMS = ['email', 'e-mail', 'mail', 'newsletter'];

    /** @var list<string> */
    protected const SOCIAL_MEDIUMS = ['social', 'social-network', 'social_network', 'social_media', 'social-media', 'organic-social', 'sm'];

    /**
     * Se compara contra el dominio registrable, así que `google` vale para
     * google.es, google.com y www.google.co.uk sin listarlos todos.
     *
     * @var list<string>
     */
    protected const SEARCH_HOSTS = ['google', 'bing', 'duckduckgo', 'yahoo', 'ecosia', 'baidu', 'yandex', 'qwant', 'brave', 'startpage'];

    /** @var list<string> */
    protected const SOCIAL_HOSTS = ['facebook', 'instagram', 'twitter', 'x', 't', 'linkedin', 'youtube', 'tiktok', 'pinterest', 'reddit', 'whatsapp', 'telegram', 'threads'];

    public function __construct(
        public LeadChannel $channel,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmTerm = null,
        public ?string $utmContent = null,
        public ?string $clickId = null,
        public ?string $referrer = null,
        public ?string $landingUrl = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $referrer = self::externalReferrer($request);
        $utmSource = self::param($request, 'utm_source', 120);
        $utmMedium = self::param($request, 'utm_medium', 120);
        $clickId = self::clickId($request);

        return new self(
            channel: self::channelFor($utmSource, $utmMedium, $clickId, $referrer),
            utmSource: $utmSource,
            utmMedium: $utmMedium,
            utmCampaign: self::param($request, 'utm_campaign', 160),
            utmTerm: self::param($request, 'utm_term', 160),
            utmContent: self::param($request, 'utm_content', 160),
            clickId: $clickId,
            referrer: $referrer,
            landingUrl: Str::limit($request->fullUrl(), 512, ''),
        );
    }

    public static function fromSession(): ?self
    {
        $stored = session()->get(self::SESSION_KEY);

        if (! is_array($stored) || ! is_string($stored['channel'] ?? null)) {
            return null;
        }

        $channel = LeadChannel::tryFrom($stored['channel']);

        return $channel === null ? null : new self(
            channel: $channel,
            utmSource: $stored['utm_source'] ?? null,
            utmMedium: $stored['utm_medium'] ?? null,
            utmCampaign: $stored['utm_campaign'] ?? null,
            utmTerm: $stored['utm_term'] ?? null,
            utmContent: $stored['utm_content'] ?? null,
            clickId: $stored['click_id'] ?? null,
            referrer: $stored['referrer'] ?? null,
            landingUrl: $stored['landing_url'] ?? null,
        );
    }

    /**
     * Guarda esta atribución como la de la visita, si no había ya una.
     *
     * Gana la primera y no la última: lo que trajo a alguien a la web es la
     * página por la que entró, no la última que miró antes de escribir.
     */
    public function remember(): void
    {
        if (session()->has(self::SESSION_KEY)) {
            return;
        }

        session()->put(self::SESSION_KEY, $this->toLeadAttributes());
    }

    /**
     * @return array<string, string|null>
     */
    public function toLeadAttributes(): array
    {
        return [
            'channel' => $this->channel->value,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'utm_term' => $this->utmTerm,
            'utm_content' => $this->utmContent,
            'click_id' => $this->clickId,
            'referrer' => $this->referrer,
            'landing_url' => $this->landingUrl,
        ];
    }

    /**
     * El canal, en el orden en que las señales se pisan unas a otras.
     *
     * Lo pagado va primero porque un anuncio en Instagram trae `fbclid` Y un
     * referrer de instagram.com: si mandara el referrer, toda la publicidad de
     * redes se contaría como redes orgánicas y el gasto no aparecería por
     * ningún lado.
     */
    protected static function channelFor(?string $source, ?string $medium, ?string $clickId, ?string $referrer): LeadChannel
    {
        $medium = $medium === null ? null : mb_strtolower($medium);
        $host = self::registrableHost($referrer);

        return match (true) {
            $clickId !== null => LeadChannel::Ads,
            in_array($medium, self::PAID_MEDIUMS, true) => LeadChannel::Ads,
            in_array($medium, self::EMAIL_MEDIUMS, true) => LeadChannel::Email,
            in_array($medium, self::SOCIAL_MEDIUMS, true) => LeadChannel::Social,
            $medium === 'organic' => LeadChannel::Organic,
            $host !== null && in_array($host, self::SOCIAL_HOSTS, true) => LeadChannel::Social,
            $host !== null && in_array($host, self::SEARCH_HOSTS, true) => LeadChannel::Organic,
            $host !== null => LeadChannel::Referral,
            $source !== null || $medium !== null => LeadChannel::Referral,
            default => LeadChannel::Direct,
        };
    }

    /**
     * El referrer, salvo cuando somos nosotros mismos.
     *
     * Un enlace interno no es un origen. Aparece aquí cuando la cookie de
     * sesión se cae a mitad de visita, y dejarlo pasar convertiría a la propia
     * web en su mayor fuente de referencias.
     */
    protected static function externalReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! is_string($referrer) || trim($referrer) === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || self::sameSite($host, (string) $request->getHost())) {
            return null;
        }

        return Str::limit($referrer, 512, '');
    }

    protected static function sameSite(string $host, string $own): bool
    {
        return self::registrable($host) === self::registrable($own);
    }

    /**
     * El dominio sin `www.` ni subdominios ni extensión: «google» para
     * www.google.es, «facebook» para m.facebook.com.
     */
    protected static function registrableHost(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? self::registrable($host) : null;
    }

    protected static function registrable(string $host): string
    {
        $parts = explode('.', mb_strtolower($host));

        // Fuera la extensión —y el `.co.uk` entero, que son dos— para quedarse
        // con el nombre. Un host sin puntos (localhost) se queda como está.
        while (count($parts) > 1 && mb_strlen(end($parts)) <= 3) {
            array_pop($parts);
        }

        return (string) end($parts);
    }

    protected static function clickId(Request $request): ?string
    {
        foreach (self::CLICK_IDS as $name) {
            $value = self::param($request, $name, 191);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected static function param(Request $request, string $name, int $limit): ?string
    {
        $value = $request->query($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $limit, '');
    }
}
