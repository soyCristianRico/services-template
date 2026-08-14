<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Lead\LeadAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota de dónde venía la visita, en la página por la que entró.
 *
 * Tiene que ser aquí y no en el envío del formulario: los formularios son
 * Livewire, así que para cuando alguien pulsa «Enviar» el referrer de esa
 * petición es la propia web y las UTMs se quedaron en una URL que ya nadie
 * mira. Lo que trajo a esa persona sólo está en su primera petición.
 *
 * Se mira la RESPUESTA y no sólo la petición porque la primera página de una
 * visita tiene que ser una página: un sitemap, una descarga, una redirección o
 * un 404 no lo son, y cualquiera de ellos gastaría el primer toque —que sólo se
 * escribe una vez— en algo que no es por donde entró nadie.
 */
final class CaptureLeadAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCapture($request, $response)) {
            LeadAttribution::fromRequest($request)->remember();
        }

        return $response;
    }

    protected function shouldCapture(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->hasHeader('X-Livewire')
            && ! $request->is('admin', 'admin/*')
            && $request->hasSession()
            && ! $request->session()->has(LeadAttribution::SESSION_KEY)
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('content-type'), 'text/html');
    }
}
