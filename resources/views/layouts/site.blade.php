@php
    /** @var \App\Modules\Storefront\Services\SiteContext $site */
    $site = app(\App\Modules\Storefront\Services\SiteContext::class);
    $metaTitle = trim($__env->yieldContent('title'));
    $metaTitle = $metaTitle !== '' ? $metaTitle.' — Optoplus' : 'Optoplus — lentile produse de noi, ochelari configurați pentru tine';
    $metaDescription = trim($__env->yieldContent('description'));
    $canonical = trim($__env->yieldContent('canonical')) ?: $site->canonical(request());
    $robots = trim($__env->yieldContent('robots')) ?: $site->robots();
    $phone = $site->setting('contact.phone_b2c');
    // Limbile: aceeași pagină în română (fără prefix), engleză (/en/) și maghiară (/hu/).
    $locale = app()->getLocale();
    $plainPath = '/'.ltrim(request()->getPathInfo(), '/');
    $queryString = request()->getQueryString();
    $langUrl = fn (string $code) => \App\Modules\I18n\Support\Locales::path($code, $plainPath).($queryString ? '?'.$queryString : '');
    $host = rtrim((string) config('optoplus.site.canonical_host'), '/');
@endphp
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    @if ($metaDescription !== '')<meta name="description" content="{{ $metaDescription }}">@endif
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="robots" content="{{ $robots }}">
    <meta property="og:site_name" content="Optoplus">
    <meta property="og:title" content="{{ $metaTitle }}">
    @if ($metaDescription !== '')<meta property="og:description" content="{{ $metaDescription }}">@endif
    <meta property="og:url" content="{{ $canonical }}">
    @unless (str_contains($robots, 'noindex'))
        @foreach (\App\Modules\I18n\Support\Locales::ALL as $code => $_)<link rel="alternate" hreflang="{{ $code }}" href="{{ $host.\App\Modules\I18n\Support\Locales::path($code, $plainPath) }}" data-no-localize>@endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $host.$plainPath }}" data-no-localize>
    @endunless
    <meta property="og:type" content="@yield('og_type', 'website')">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <meta name="theme-color" content="#053b5e">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @stack('head')
    @foreach ($site->jsonLd() as $ld)
        <script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endforeach
    @stack('jsonld')
    @if (\App\Modules\I18n\Support\Locales::isTranslated($locale))
        <script type="application/json" id="i18n-js" nonce="{{ app('csp-nonce') }}">{!! json_encode(\App\Modules\I18n\Support\JsStrings::for($locale), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif
</head>
<body class="@yield('body_class')">
<a class="skip" href="#continut">Sari la conținut</a>
@if ($site->isStaging())
    <div class="stage-band" role="status">Mediu de test — comenzile și plățile nu sunt reale.</div>
@endif
@php($topbar = app(\App\Core\Settings\Settings::class)->get('site.topbar_text'))
@if ($topbar)
    @php($topbarUrl = \App\Modules\Cms\Services\BlockRegistry::url((string) app(\App\Core\Settings\Settings::class)->get('site.topbar_url')))
    <div class="topbar">@if ($topbarUrl)<a href="{{ $topbarUrl }}">{{ $topbar }}</a>@else<span>{{ $topbar }}</span>@endif</div>
@endif
<header class="site-header" data-module="header">
    <div class="site-header__bar wrap">
        <button class="icon-btn site-header__menu-btn" type="button" aria-expanded="false" aria-controls="meniu-principal" data-menu-toggle>
            <x-site.icon name="menu" /><span class="sr-only">Meniu</span>
        </button>
        <a class="brand" href="/" aria-label="Optoplus — prima pagină">
            <img src="/images/optoplus-logo.webp" alt="Optoplus — Expertul vederii" width="141" height="48">
        </a>
        <nav class="main-nav" id="meniu-principal" aria-label="Navigare principală" data-menu>
            <div class="main-nav__head">
                <span class="main-nav__title">Meniu</span>
                <button class="icon-btn" type="button" data-menu-close><x-site.icon name="close" /><span class="sr-only">Închide meniul</span></button>
            </div>
            <ul class="main-nav__list">
                @foreach ($site->menu('header') as $item)
                    <li class="main-nav__item @if($item['children']) has-children @endif">
                        @if ($item['children'])
                            <details class="main-nav__group">
                                <summary class="main-nav__link @if($site->isActive($item['url'])) is-active @endif">{{ $item['label'] }}<x-site.icon name="chevron-down" :size="18" /></summary>
                                <ul class="main-nav__sub">
                                    <li><a href="{{ $item['url'] }}">Toate: {{ mb_strtolower($item['label']) }}</a></li>
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['url'] }}">{{ $child['label'] }}@if($child['description'])<small>{{ $child['description'] }}</small>@endif</a></li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <a class="main-nav__link @if($site->isActive($item['url'])) is-active @endif" href="{{ $item['url'] }}" @if($site->isActive($item['url'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
            <nav class="lang-switch lang-switch--menu" aria-label="Limba site-ului / Language / Nyelv" translate="no">
                @foreach (\App\Modules\I18n\Support\Locales::ALL as $code => $name)
                    <a href="{{ $langUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" data-no-localize @if($code === $locale) aria-current="true" @endif>{{ $name }}</a>
                @endforeach
            </nav>
            @if ($phone)
                <a class="main-nav__phone" href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"><x-site.icon name="phone" :size="18" />Comandă la telefon: {{ $phone }}</a>
            @endif
        </nav>
        <div class="site-header__tools">
            {{-- Comutatorul de limbă: pe desktop RO · EN · HU; pe telefon un buton compact cu listă. --}}
            <nav class="lang-switch lang-switch--bar" aria-label="Limba site-ului / Language / Nyelv" translate="no">
                @foreach (\App\Modules\I18n\Support\Locales::ALL as $code => $name)
                    <a href="{{ $langUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}" data-no-localize @if($code === $locale) aria-current="true" @endif>{{ strtoupper($code) }}</a>
                @endforeach
            </nav>
            <details class="lang-drop" translate="no">
                <summary class="icon-btn lang-drop__btn" aria-label="Limba / Language / Nyelv"><x-site.icon name="globe" :size="20" /><span>{{ strtoupper($locale) }}</span></summary>
                <div class="lang-drop__menu">
                    @foreach (\App\Modules\I18n\Support\Locales::ALL as $code => $name)
                        <a href="{{ $langUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" data-no-localize @if($code === $locale) aria-current="true" @endif>{{ $name }}</a>
                    @endforeach
                </div>
            </details>
            <a class="icon-btn search-link" href="/cautare/"><x-site.icon name="search" /><span class="sr-only">Caută</span></a>
            <a class="icon-btn" href="{{ $site->accountUrl() }}"><x-site.icon name="user" /><span class="sr-only">{{ $site->customer() ? 'Contul meu' : 'Autentificare' }}</span></a>
            <a class="icon-btn cart-link" href="/cos/"><x-site.icon name="bag" /><span class="sr-only">Coșul</span>@if($n = $site->cartCount())<span class="cart-link__count" aria-label="{{ $n }} produse în coș">{{ $n }}</span>@endif</a>
        </div>
    </div>
</header>

<main id="continut" tabindex="-1">
    @if (session('status'))
        <div class="wrap"><div class="notice notice--ok" role="status"><x-site.icon name="check" />{{ session('status') }}</div></div>
    @endif
    @yield('content')
</main>

{{-- În pașii configuratorului bara cu totalul ține locul barei de acțiuni (nu se suprapun). --}}
@if (! request()->is('configurator/*/pas/*') && ($phone || ! request()->is('configurator*', 'comanda*', 'cos*')))
    <div class="mobile-bar" aria-label="Acțiuni rapide">
        @if ($phone)<a class="mobile-bar__btn" href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"><x-site.icon name="phone" :size="20" />Sună</a>@endif
        @unless (request()->is('configurator*', 'comanda*', 'cos*'))<a class="mobile-bar__btn mobile-bar__btn--primary" href="/configurator/"><x-site.icon name="lens" :size="20" />Configurează ochelarii</a>@endunless
    </div>
@endif

<footer class="site-footer">
    <div class="wrap site-footer__grid">
        <div class="site-footer__brand">
            <img src="/images/optoplus-logo-alb.png" alt="Optoplus" width="141" height="48" loading="lazy">
            <p>Lentile produse în laboratorul nostru. Rame alese pentru tine.</p>
            @if ($phone || $site->setting('contact.email_b2c'))
                <ul class="site-footer__contact">
                    @if ($phone)<li><a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}">{{ $phone }}</a></li>@endif
                    @if ($email = $site->setting('contact.email_b2c'))<li><a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                    @if ($addr = $site->setting('contact.address'))<li>{{ $addr }}</li>@endif
                </ul>
            @endif
        </div>
        @foreach (['footer' => 'Optoplus', 'footer_legal' => 'Informații legale'] as $menu => $heading)
            @if ($items = $site->menu($menu))
                <nav class="site-footer__col" aria-label="{{ $heading }}">
                    <h2>{{ $heading }}</h2>
                    <ul>@foreach ($items as $item)<li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>@endforeach</ul>
                </nav>
            @endif
        @endforeach
    </div>
    <div class="wrap site-footer__legal">
        @if ($company = $site->companyLine())<p>{{ $company }}</p>@endif
        <p>© {{ date('Y') }} Optoplus. {{ \App\Core\Support\Tax::note() }}@if(\App\Core\Support\Tax::payer()) Excepție: prețurile afișate partenerilor ca „fără TVA”.@endif</p>
        <p><a href="https://anpc.ro/ce-este-sal/" rel="noopener">ANPC — Soluționarea alternativă a litigiilor (SAL)</a> · <a href="https://ec.europa.eu/consumers/odr" rel="noopener">Soluționarea online a litigiilor (SOL)</a></p>
        <p class="site-footer__credit">Powered by <a href="https://vitim.ro" rel="noopener" target="_blank"><strong>VITIM</strong></a></p>
    </div>
</footer>
@stack('scripts')
</body>
</html>
