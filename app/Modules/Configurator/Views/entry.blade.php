@extends('layouts.site')
@section('title', 'Configurează ochelarii')
@section('description', 'Configurează ochelarii de vedere în 5 pași: rama, rețeta, lentila produsă în laboratorul Optoplus, tratamentele și prețul final, vizibil la fiecare pas.')
@section('content')
<div class="wrap">
    <x-site.breadcrumbs :items="['Configurează ochelarii' => null]" />
    <header class="page-hero page-hero--bleu">
        <div class="page-hero__text">
            <h1>Configurează ochelarii</h1>
            <p class="lead">Cinci pași scurți, cu prețul final vizibil tot timpul. Te poți opri oricând: configurația rămâne salvată.</p>
        </div>
        <ol class="step-pills" aria-label="Pașii configurării">
            <li>Rama</li><li>Rețeta</li><li>Lentila</li><li>Tratamente</li><li>Prețul final</li>
        </ol>
    </header>
    @if ($recent->isNotEmpty())
        <div class="box resume-box">
            <h2>Continuă de unde ai rămas</h2>
            <ul class="line__parts" style="font-size: 1rem">
                @foreach ($recent as $r)<li><a href="{{ $r->stepUrl($r->step) }}">{{ $r->frameVariant?->displayName() ?? 'Doar lentile' }}</a><span class="muted">pasul {{ $r->step }} din 5 · {{ $r->updated_at->diffForHumans() }}</span></li>@endforeach
            </ul>
        </div>
    @endif
    <div class="paths">
        <a class="path path--azur" href="/rame/">
            <span class="path__icon"><x-site.icon name="frame" :size="30" /></span>
            <strong class="path__title">Aleg rama și adaug lentile</strong>
            <span class="path__text">Alegi rama din catalog, apoi lentilele potrivite rețetei tale.</span>
            <span class="btn btn--primary path__btn">Vezi ramele<x-site.icon name="chevron-right" :size="18" /></span>
        </a>
        <form method="post" action="/configurator/start/" class="path path--verde">@csrf<input type="hidden" name="intrare" value="fara_rama">
            <span class="path__icon"><x-site.icon name="lens" :size="30" /></span>
            <strong class="path__title">Aleg doar lentile</strong>
            <span class="path__text">Pentru o ramă pe care o ai deja la un optician, sau lentile de rezervă.</span>
            <button class="btn btn--primary path__btn" type="submit">Începe<x-site.icon name="chevron-right" :size="18" /></button>
        </form>
        @if ($ownFrame)
            <form method="post" action="/configurator/start/" class="path path--portocaliu">@csrf<input type="hidden" name="intrare" value="rama_mea">
                <span class="path__icon"><x-site.icon name="repeat" :size="30" /></span>
                <strong class="path__title">Lentile pentru rama mea</strong>
                <span class="path__text">Ne trimiți rama ta, noi montăm lentilele.</span>
                <button class="btn btn--primary path__btn" type="submit">Începe<x-site.icon name="chevron-right" :size="18" /></button>
            </form>
        @endif
        <a class="path path--mov" href="/configurator/ajutor/">
            <span class="path__icon"><x-site.icon name="chat" :size="30" /></span>
            <strong class="path__title">Am nevoie de ajutor</strong>
            <span class="path__text">Trimite rețeta și câteva informații; un specialist te contactează cu o recomandare.</span>
            <span class="btn path__btn">Cere o recomandare<x-site.icon name="chevron-right" :size="18" /></span>
        </a>
    </div>
</div>
@endsection
