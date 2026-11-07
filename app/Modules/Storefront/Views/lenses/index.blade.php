@extends('layouts.site')
@section('title', 'Lentile de vedere produse în laboratorul nostru')
@section('description', 'Lentile monofocale, progresive, degresive și bifocale produse de Optoplus, cu indici de subțiere și tratamente. Prețuri pe pereche, configurare după rețetă.')
@section('content')
<div class="wrap">
    <x-site.breadcrumbs :items="['Lentile' => null]" />
    <header class="page-hero page-hero--bleu">
        <div class="page-hero__text">
            <h1>Lentile produse de noi</h1>
            <p class="lead">Lentilele de mai jos se fac în laboratorul Optoplus, după rețeta ta. Alegi familia potrivită pentru cum folosești ochelarii, apoi grosimea și tratamentele; un specialist verifică fiecare comandă înainte de producție.</p>
            <div class="btn-row"><a class="btn btn--primary" href="/configurator/">Configurează ochelarii</a><a class="btn" href="/ghiduri/">Ghid: ce lentile mi se potrivesc?</a></div>
        </div>
        @if (count($families) > 1)
            <nav class="cat-nav" aria-label="Familii de lentile">
                @foreach ($families as $f)<a class="cat-nav__link" href="#{{ $f['family']->code }}">{{ $f['family']->name }}</a>@endforeach
            </nav>
        @endif
    </header>
    @php($tones = ['bleu', 'piersica', 'menta', 'lavanda', 'galben'])
    @foreach ($families as $f)
        <section class="lens-fam lens-fam--{{ $tones[$loop->index % count($tones)] }}" id="{{ $f['family']->code }}" aria-labelledby="fam-{{ $f['family']->code }}">
            <div class="lens-fam__intro">
                @include('storefront::partials.family-diagram', ['code' => $f['family']->code])
                <h2 id="fam-{{ $f['family']->code }}">Lentile {{ mb_strtolower($f['family']->name) }}</h2>
                <p>{{ $f['family']->customer_description }}</p>
            </div>
            <div class="lens-fam__list">
                @foreach ($f['products'] as $p)
                    <a class="lens-offer" href="{{ $p->url() }}">
                        @if($p->made_in_house)<span class="tag tag--lab"><x-site.icon name="lab" :size="16" />Produsă de Optoplus</span>@else<span class="tag">{{ $p->lens->supplier_name ?: 'Producător terț' }}</span>@endif
                        <strong class="lens-offer__name">{{ $p->name }}</strong>
                        <span class="lens-offer__text">{{ $p->lens->customer_summary }}</span>
                        <span class="lens-offer__meta">Indici: {{ $p->lens->offerings->map(fn ($o) => $o->material->indexLabel())->implode(' · ') }}</span>
                        <span class="lens-offer__foot"><span class="lens-offer__price">de la {{ \App\Core\Support\Money::format($prices[$p->id]) }} <small>/ {{ $p->sale_unit === 'pair' ? 'pereche' : 'bucată' }}</small></span><span class="lens-offer__go">Detalii<x-site.icon name="chevron-right" :size="18" /></span></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
    <p class="diagram-note">Schemele zonelor de vedere sunt ilustrative, nu la scară.</p>
</div>
@endsection
