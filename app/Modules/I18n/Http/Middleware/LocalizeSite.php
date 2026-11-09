<?php

namespace App\Modules\I18n\Http\Middleware;

use App\Modules\I18n\Services\Dictionary;
use App\Modules\I18n\Services\HtmlTranslator;
use App\Modules\I18n\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /en/… și /hu/…: scoate prefixul limbii înainte de rutare (aplicația vede aceleași adrese ca în română), setează
 * limba, apoi traduce răspunsul: pagini HTML, răspunsurile JSON ale configuratorului și redirecționările.
 * Panoul și webhookurile nu sunt atinse.
 */
class LocalizeSite
{
    public function __construct(private HtmlTranslator $translator, private Dictionary $dictionary) {}

    public function handle(Request $request, Closure $next): Response
    {
        [$locale, $path] = Locales::split('/'.ltrim($request->getPathInfo(), '/'));
        if ($locale === Locales::DEFAULT) {
            return $next($request);
        }
        $query = $request->getQueryString();
        $request->server->set('REQUEST_URI', $path.($query !== null ? '?'.$query : ''));
        $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(),
            $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent());
        $request->attributes->set('locale', $locale);
        app()->setLocale($locale);
        Carbon::setLocale($locale);
        if ($request->is('panou', 'panou/*', 'webhook/*')) {
            return redirect($path.($query !== null ? '?'.$query : ''));
        }
        $this->dictionary->context = $path;
        $this->translator->hosts = [$request->getSchemeAndHttpHost()];
        $this->dictionary->collect = (bool) config('optoplus.i18n.collect', false) && $request->isMethod('GET')
            && (config('optoplus.i18n.collect_private', false) || ! $request->is('cont', 'cont/*', 'comanda/*', 'portal', 'portal/*', 'c/*'));

        $response = $next($request);

        return $this->localize($response, $locale);
    }

    /** Textele netraduse găsite (doar cu colectarea activă) se scriu după trimiterea răspunsului. */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->dictionary->collect) {
            $this->dictionary->flushMissing();
        }
    }

    private function localize(Response $response, string $locale): Response
    {
        if ($response instanceof RedirectResponse) {
            $response->setTargetUrl($this->translator->url($response->getTargetUrl(), $locale));

            return $response;
        }
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                array_walk_recursive($data, function (&$value, $key) use ($locale) {
                    if (is_string($value) && preg_match('/\p{L}/u', $value)) {
                        $value = str_contains($value, '<') ? $this->translator->translate($value, $locale) : $this->translator->plain($value, $locale);
                    }
                });
                $response->setData($data);
            }

            return $response;
        }
        $type = (string) $response->headers->get('Content-Type');
        if ($response->isSuccessful() || $response->isClientError() || $response->getStatusCode() === 503) {
            if (str_starts_with($type, 'text/html') && ! method_exists($response, 'getFile')) {
                $content = $response->getContent();
                if (is_string($content) && $content !== '') {
                    $response->setContent($this->translator->translate($content, $locale));
                }
            }
        }

        return $response;
    }
}
