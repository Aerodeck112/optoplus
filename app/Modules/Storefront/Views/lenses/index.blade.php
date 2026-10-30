@extends('layouts.site')
@section('title', 'Lentile de vedere produse în laboratorul nostru')
@section('description', 'Lentile monofocale, progresive, degresive și bifocale produse de Optoplus, cu indici de subțiere și tratamente. Prețuri pe pereche, configurare după rețetă.')
@section('content')
<div class="wrap">
    <x-site.breadcrumbs :items="['Lentile' => null]" />
    <header class="page-head">
        <h1>Lentile produse de noi</h1>
        <p class="lead">Lentilele de mai jos se fac în laboratorul Optoplus, după rețeta ta. Alegi familia potrivită pentru cum folosești ochelarii, apoi grosimea și tratamentele; un specialist verifică fiecare comandă înainte de producție.</p>
        <div class="btn-row"><a class="btn btn--primary" href="/configurator/">Configurează ochelarii</a><a class="btn" href="/ghiduri/">Ghid: ce lentile mi se potrivesc?</a></div>
    </header>
    @foreach ($families as $f)
        <section class="section" style="padding-top: 24px" id="{{ $f['family']->code }}" aria-labelledby="fam-{{ $f['family']->code }}">
            <div class="story" style="align-items: start">
                <div>
                    @include('storefront::partials.family-diagram', ['code' => $f['family']->code])
                    <h2 id="fam-{{ $f['family']->code }}" style="margin-top: 16px">Lentile {{ mb_strtolower($f['family']->name) }}</h2>
                    <p>{{ $f['family']->customer_description }}</p>
                </div>
                <div class="grid" style="grid-template-columns: 1fr">
                    @foreach ($f['products'] as $p)
                        <a class="entry" href="{{ $p->url() }}">
                            <span>@if($p->made_in_house)<span class="tag tag--lab"><x-site.icon name="lab" :size="16" />Produsă de Optoplus</span>@else<span class="tag">{{ $p->lens->supplier_name ?: 'Producător terț' }}</span>@endif</span>
                            <strong>{{ $p->name }}</strong>
                            <span>{{ $p->lens->customer_summary }}</span>
                            <span>Indici: {{ $p->lens->offerings->map(fn ($o) => $o->material->indexLabel())->implode(' · ') }}</span>
                            <span class="card__price">de la {{ \App\Core\Support\Money::format($prices[$p->id]) }} / {{ $p->sale_unit === 'pair' ? 'pereche' : 'bucată' }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
    <p class="diagram-note">Schemele zonelor de vedere sunt ilustrative, nu la scară.</p>
</div>
@endsection
