@extends('configurator::partials.shell')
@section('step')
<form method="post" action="/configurator/{{ $cfg->uuid }}/pas/4/" class="form">
    @csrf
    <h1>Tratamente</h1>
    @php($available = $offering?->treatments->filter(fn ($t) => $t->treatment?->is_active && $t->status !== 'inactiv') ?? collect())
    @if ($available->isEmpty())
        <p>Lentila aleasă (<strong>{{ $offering?->product->name }}</strong>) nu are tratamente opționale. Poți continua la rezumat sau alege altă lentilă.</p>
    @else
        <p>Tratamentele sunt opționale. Îți spunem ce fac și ce nu fac, ca să alegi doar ce îți folosește.</p>
        @php($chosen = collect($cfg->treatments ?? [])->keyBy('treatment_id'))
        @php($blocked = $blockedTreatments ?? [])
        <div class="choice-grid">
            @foreach ($available as $ot)
                @php($t = $ot->treatment)
                @php($why = $blocked[$t->id] ?? null)
                <div class="choice @if($why) choice--blocked @endif" style="cursor: default">
                    <label class="check" style="flex: 1">
                        <input type="checkbox" name="tratamente[]" value="{{ $t->id }}" @checked(! $why && $chosen->has($t->id)) @disabled($why) data-calc data-treatment="{{ $t->id }}" @if($t->requires_option) data-needs-option @endif>
                        <span class="choice__body">
                            <span class="choice__title"><span>{{ $t->name }}</span><span>@if($ot->is_included) inclus @else +{{ \App\Core\Support\Money::format($ot->price) }} @endif</span></span>
                            <span class="choice__text"><strong>Ce face:</strong> {{ $t->benefits }}</span>
                            <span class="choice__text"><strong>Limite:</strong> {{ $t->limits }}</span>
                            @if ($why)<span class="choice__blocked"><strong>Nu este disponibil pentru alegerea ta:</strong> {{ $why }}</span>@endif
                        </span>
                    </label>
                    @unless ($why)
                    @include('configurator::partials.treatment-explainer', ['kind' => $t->kind, 'treatmentId' => $t->id])
                    @endunless
                    @if ($t->requires_option && ! $why)
                        @php($opts = $t->options->filter(fn ($op) => $op->is_active && $ot->allowsOption($op->code)))
                        <fieldset class="variants" data-option-for="{{ $t->id }}" style="margin-top: 12px; width: 100%">
                            <legend>Alege nuanța <span class="muted">(obligatoriu)</span></legend>
                            @foreach ($opts as $op)
                                <label><input type="radio" name="optiune[{{ $t->id }}]" value="{{ $op->code }}" @checked(($chosen[$t->id]['option'] ?? null) === $op->code) data-calc>@if($op->swatch)<span aria-hidden="true" style="display:inline-block;width:14px;height:14px;border-radius:50%;background:{{ $op->swatch }}"></span>@endif{{ $op->label }}</label>
                            @endforeach
                        </fieldset>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="notice notice--error" role="alert" data-calc-errors hidden></div>
    @endif
    <div class="cfg__nav">
        <a class="btn btn--ghost" href="{{ $cfg->stepUrl(3) }}">Înapoi la lentile</a>
        <button class="btn btn--primary" type="submit">Vezi rezumatul</button>
    </div>
</form>
@endsection
