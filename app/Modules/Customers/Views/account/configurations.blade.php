@extends('customers::account.shell')
@section('title', 'Configurații salvate')
@section('heading', 'Configurații salvate')
@section('body')
@forelse ($configs as $c)
    <article class="box line-flat">
        <div>
            <strong class="line__title">{{ $c->frameVariant?->displayName() ?? ($c->entry === 'fara_rama' ? 'Doar lentile' : 'Configurație') }}</strong>
            <p class="small muted">{{ $c->offering ? $c->offering->product->name.', indice '.$c->offering->material->indexLabel() : 'Lentila nu a fost aleasă încă' }} · actualizată {{ $c->updated_at->format('d.m.Y') }} · pasul {{ $c->step }} din 5</p>
        </div>
        <div class="line__actions">
            <a class="btn btn--small btn--primary" href="{{ $c->stepUrl($c->step) }}">Continuă</a>
            <form method="post" action="/cont/configuratii/{{ $c->uuid }}/sterge/">@csrf<button class="link-btn" type="submit">Șterge</button></form>
        </div>
    </article>
@empty
    <div class="empty"><p>Nu aveți configurații salvate.</p><p><a class="btn btn--primary" href="/configurator/">Configurează ochelarii</a></p></div>
@endforelse
@endsection
