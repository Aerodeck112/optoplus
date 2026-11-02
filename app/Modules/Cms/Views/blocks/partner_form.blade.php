{{-- Cererea de parteneriat: formularul într-un card, alături de pașii care urmează și datele de contact B2B. --}}
@php($s = app(\App\Core\Settings\Settings::class))
@php($phone = $s->get('contact.phone_b2b') ?: $s->get('contact.phone_b2c'))
@php($email = $s->get('contact.email_b2b') ?: $s->get('contact.email_b2c'))
<section class="section section--form" id="cerere">
    <div class="wrap partner-form">
        <div class="partner-form__card">
            @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
            @if (! empty($d['text']))<p>{{ $d['text'] }}</p>@endif
            @include('storefront::forms.partner')
        </div>
        <aside class="partner-form__aside">
            <h3>Ce urmează după cerere</h3>
            <ol class="mini-steps">
                <li><strong>Verificăm cererea</strong><span>Un coleg verifică manual datele firmei.</span></li>
                <li><strong>Primiți invitația</strong><span>Administratorul organizației primește pe email linkul de activare a contului.</span></li>
                <li><strong>Adăugați colegii</strong><span>Fiecare coleg are rolul lui: comenzi, documente financiare sau doar vizualizare.</span></li>
                <li><strong>Comandați</strong><span>Comandă rapidă în tabel, import din CSV sau „comandă din nou” pentru lucrările repetate.</span></li>
            </ol>
            @if ($phone || $email)
                <div class="partner-form__contact">
                    <p>Preferați să vorbim direct?</p>
                    @if ($phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"><x-site.icon name="phone" :size="18" />{{ $phone }}</a>@endif
                    @if ($email)<a href="mailto:{{ $email }}"><x-site.icon name="chat" :size="18" />{{ $email }}</a>@endif
                </div>
            @endif
        </aside>
    </div>
</section>
