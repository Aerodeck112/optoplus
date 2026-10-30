<?php

namespace App\Modules\Storefront\Services;

use App\Core\Settings\Settings;
use App\Modules\Cms\Models\MenuItem;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Datele comune ale paginilor publice: meniuri, setări de contact, URL canonic, robots, contorul coșului.
 * Meniurile se citesc din cache (invalidat la salvarea lor din panou).
 */
class SiteContext
{
    /** Parametrii de interogare care rămân în URL-ul canonic (paginarea); filtrele și sortarea nu. */
    private const CANONICAL_PARAMS = ['pagina'];

    private ?array $jsonLd = null;

    public function __construct(private Settings $settings) {}

    public function setting(string $key, mixed $default = null): mixed
    {
        $v = $this->settings->get($key, $default);

        return $v === '' ? $default : $v;
    }

    /** @return list<array{label:string, url:string, description:?string, children:list<array>}> */
    public function menu(string $menu): array
    {
        return Cache::remember('site.menu.'.$menu, 600, function () use ($menu) {
            $items = MenuItem::where('menu', $menu)->where('is_visible', true)->orderBy('sort')->get();
            $tree = [];
            foreach ($items->whereNull('parent_id') as $item) {
                $tree[] = [
                    'label' => $item->label, 'url' => $item->url ?: '#', 'description' => $item->description,
                    'children' => $items->where('parent_id', $item->id)->values()->map(fn ($c) => [
                        'label' => $c->label, 'url' => $c->url ?: '#', 'description' => $c->description, 'children' => [],
                    ])->all(),
                ];
            }

            return $tree;
        });
    }

    public static function forgetMenus(): void
    {
        foreach (['header', 'footer', 'footer_legal'] as $m) {
            Cache::forget('site.menu.'.$m);
        }
    }

    public function isActive(string $url): bool
    {
        $path = '/'.ltrim(request()->path(), '/');
        $url = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return $url !== '' && ($path === $url || str_starts_with($path.'/', $url.'/'));
    }

    public function canonical(Request $request): string
    {
        $base = rtrim((string) config('optoplus.site.canonical_host', config('app.url')), '/');
        $path = '/'.ltrim($request->path(), '/');
        if ($path !== '/' && ! str_ends_with($path, '/') && ! preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
            $path .= '/';
        }
        $query = array_intersect_key($request->query(), array_flip(self::CANONICAL_PARAMS));

        return $base.$path.($query ? '?'.http_build_query($query) : '');
    }

    public function robots(): string
    {
        return $this->isStaging() ? 'noindex, nofollow' : 'index, follow, max-image-preview:large';
    }

    public function isStaging(): bool
    {
        return config('optoplus.stage') === 'staging';
    }

    public function customer(): ?Customer
    {
        return Auth::guard('client')->user();
    }

    public function accountUrl(): string
    {
        return $this->customer() ? '/cont/' : '/cont/autentificare/';
    }

    public function cartCount(): int
    {
        $uuid = request()->cookie('optoplus_cos');
        if (! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            return 0;
        }

        return (int) DB::table('cart_items')->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->where('carts.uuid', $uuid)->sum('cart_items.quantity');
    }

    /** Linia cu datele firmei; goală până la confirmarea datelor (docs/INTREBARI.md #1). */
    public function companyLine(): ?string
    {
        $parts = array_filter([
            $this->setting('company.name'),
            ($c = $this->setting('company.vat_code')) ? 'CUI '.$c : null,
            ($r = $this->setting('company.registry')) ? 'Reg. Com. '.$r : null,
            $this->setting('company.address'),
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    /** Datele structurate comune (Organization, WebSite cu căutare). */
    public function jsonLd(): array
    {
        if ($this->jsonLd !== null) {
            return $this->jsonLd;
        }
        $base = rtrim((string) config('optoplus.site.canonical_host', config('app.url')), '/');
        $org = [
            '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Optoplus', 'url' => $base.'/',
            'logo' => $base.'/images/optoplus-logo.png',
        ];
        if ($legal = $this->setting('company.name')) {
            $org['legalName'] = $legal;
        }
        if ($phone = $this->setting('contact.phone_b2c')) {
            $org['contactPoint'] = ['@type' => 'ContactPoint', 'telephone' => $phone, 'contactType' => 'customer service', 'availableLanguage' => ['ro']];
        }
        $website = [
            '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Optoplus', 'url' => $base.'/',
            'potentialAction' => ['@type' => 'SearchAction', 'target' => $base.'/cautare/?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
        ];

        return $this->jsonLd = request()->is('/') ? [$org, $website] : [];
    }
}
