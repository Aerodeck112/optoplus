@extends('layouts.site')
@section('title', 'Configurează ochelarii')
@section('description', 'Configurează ochelarii de vedere în 5 pași: rama, rețeta, lentila produsă în laboratorul Optoplus, tratamentele și prețul final, vizibil la fiecare pas.')
@section('content')
<div class="wrap section" style="padding-top: 24px">
    <x-site.breadcrumbs :items="['Configurează ochelarii' => null]" />
    <h1>Configurează ochelarii</h1>
    <p class="lead">Cinci pași scurți, cu prețul final vizibil tot timpul. Te poți opri oricând: configurația rămâne salvată.</p>
    @if ($recent->isNotEmpty())
        <div class="box" style="margin: 24px 0">
            <h2>Continuă de unde ai rămas</h2>
            <ul class="line__parts" style="font-size: 1rem">
                @foreach ($recent as $r)<li><a href="{{ $r->stepUrl($r->step) }}">{{ $r->frameVariant?->displayName() ?? 'Doar lentile' }}</a><span class="muted">pasul {{ $r->step }} din 5 · {{ $r->updated_at->diffForHumans() }}</span></li>@endforeach
            </ul>
        </div>
    @endif
    <div class="entry-grid">
        <a class="entry" href="/rame/"><x-site.icon name="frame" :size="32" /><strong>Aleg rama și adaug lentile</strong><span>Alegi rama din catalog, apoi lentilele potrivite rețetei tale.</span></a>
        <form method="post" action="/configurator/start/" class="entry" style="margin: 0">@csrf<input type="hidden" name="intrare" value="fara_rama"><x-site.icon name="lens" :size="32" /><strong>Aleg doar lentile</strong><span>Pentru o ramă pe care o ai deja la un optician, sau lentile de rezervă.</span><button class="btn btn--small" type="submit" style="justify-self: start; margin-top: 8px">Începe</button></form>
        @if ($ownFrame)
            <form method="post" action="/configurator/start/" class="entry" style="margin: 0">@csrf<input type="hidden" name="intrare" value="rama_mea"><x-site.icon name="repeat" :size="32" /><strong>Lentile pentru rama mea</strong><span>Ne trimiți rama ta, noi montăm lentilele.</span><button class="btn btn--small" type="submit" style="justify-self: start; margin-top: 8px">Începe</button></form>
        @endif
        <a class="entry" href="/configurator/ajutor/"><x-site.icon name="chat" :size="32" /><strong>Am nevoie de ajutor</strong><span>Trimite rețeta și câteva informații; un specialist te contactează cu o recomandare.</span></a>
    </div>
</div>
@endsection
