@extends('configurator::partials.shell')
@section('step')
<form method="post" action="/configurator/{{ $cfg->uuid }}/pas/1/" class="form">
    @csrf
    @if ($frame)
        <p class="split-note" style="margin: 0"><x-site.icon name="frame" /><span>Rama aleasă: <strong>{{ $frame->displayName() }}</strong> · <a href="{{ $frame->product->url() }}">schimbă rama</a></span></p>
    @endif
    <h1>Pentru ce folosești ochelarii?</h1>
    <p>Alegerea ne ajută să îți arătăm întâi lentilele potrivite. Nu este un diagnostic: valorile corecției le stabilește rețeta ta.</p>
    <fieldset style="border: 0; padding: 0; margin: 0">
        <legend class="sr-only">Utilizarea principală</legend>
        <div class="choice-grid">
            @foreach ([
                'distanta' => ['Distanță', 'Condus, televizor, privit la depărtare. Rețeta are de obicei o singură serie de valori.'],
                'aproape' => ['Aproape', 'Citit, telefon, lucru de aproape (ochelari de citit).'],
                'calculator' => ['Calculator și birou', 'Ecran și documente, la distanță de un braț; activități în interior.'],
                'toate' => ['Toate distanțele', 'O singură pereche pentru aproape și departe. Rețeta are adiție (ADD).'],
                'nu_stiu' => ['Nu știu încă', 'Îți arătăm toate variantele, cu explicații.'],
            ] as $value => [$title, $text])
                <label class="choice"><input type="radio" name="utilizare" value="{{ $value }}" @checked(old('utilizare', $cfg->usage) === $value) required><span class="choice__mark"></span>
                    <span class="choice__body"><span class="choice__title">{{ $title }}</span><span class="choice__text">{{ $text }}</span></span></label>
            @endforeach
        </div>
    </fieldset>
    @if ($cfg->entry === 'fara_rama')
        <fieldset style="border: 0; padding: 0; margin: 16px 0 0">
            <legend class="label">Pentru ce ochi comanzi lentile?</legend>
            <div class="choice-grid choice-grid--2">
                @php($eyes = $input->eyes)
                @foreach (['ambii' => 'Ambii ochi (pereche)', 'od' => 'Doar ochiul drept', 'os' => 'Doar ochiul stâng'] as $v => $l)
                    <label class="choice"><input type="radio" name="ochi" value="{{ $v }}" @checked(old('ochi', $eyes) === $v)><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">{{ $l }}</span></span></label>
                @endforeach
            </div>
            <p class="small muted">Unele lentile se vând doar în pereche; îți spunem la pasul „Lentila”.</p>
        </fieldset>
    @endif
    <div class="cfg__nav">
        <a class="btn btn--ghost" href="/configurator/">Înapoi</a>
        <button class="btn btn--primary" type="submit">Continuă la rețetă</button>
    </div>
</form>
@endsection
