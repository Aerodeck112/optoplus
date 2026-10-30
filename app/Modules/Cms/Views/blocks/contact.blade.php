@php($site = app(\App\Modules\Storefront\Services\SiteContext::class))
<section class="section" style="padding-top: 8px">
    <div class="wrap layout-2">
        <div class="box">
            <h2>Scrieți-ne</h2>
            @if (! empty($d['text']))<p>{{ $d['text'] }}</p>@endif
            @include('storefront::forms.contact')
        </div>
        <aside class="box">
            <h2>Date de contact</h2>
            <dl class="facts">
                @if ($p = $site->setting('contact.phone_b2c'))<dt>Telefon</dt><dd><a href="tel:{{ preg_replace('/[^+\d]/', '', $p) }}">{{ $p }}</a></dd>@endif
                @if ($e = $site->setting('contact.email_b2c'))<dt>Email</dt><dd><a href="mailto:{{ $e }}">{{ $e }}</a></dd>@endif
                @if ($a = $site->setting('contact.address'))<dt>Adresă</dt><dd>{{ $a }}</dd>@endif
                @if ($h = $site->setting('contact.hours'))<dt>Program</dt><dd>{{ $h }}</dd>@endif
            </dl>
            @if ($site->setting('contact.phone_b2b') || $site->setting('contact.email_b2b'))
                <h3 style="margin-top: 24px">Pentru parteneri (B2B)</h3>
                <dl class="facts">
                    @if ($p = $site->setting('contact.phone_b2b'))<dt>Telefon</dt><dd><a href="tel:{{ preg_replace('/[^+\d]/', '', $p) }}">{{ $p }}</a></dd>@endif
                    @if ($e = $site->setting('contact.email_b2b'))<dt>Email</dt><dd><a href="mailto:{{ $e }}">{{ $e }}</a></dd>@endif
                </dl>
            @endif
        </aside>
    </div>
</section>
