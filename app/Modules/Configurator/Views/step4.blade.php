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
        <div class="choice-grid">
            @foreach ($available as $ot)
                @php($t = $ot->treatment)
                <div class="choice" style="cursor: default">
                    <label class="check" style="flex: 1">
                        <input type="checkbox" name="tratamente[]" value="{{ $t->id }}" @checked($chosen->has($t->id)) data-calc data-treatment="{{ $t->id }}">
                        <span class="choice__body">
                            <span class="choice__title"><span>{{ $t->name }}</span><span>@if($ot->is_included) inclus @else +{{ \App\Core\Support\Money::format($ot->price) }} @endif</span></span>
                            <span class="choice__text"><strong>Ce face:</strong> {{ $t->benefits }}</span>
                            <span class="choice__text"><strong>Limite:</strong> {{ $t->limits }}</span>
                            @if ($t->kind === 'fotocromatic')<span class="illustrative">Comparația interior/exterior de pe site este o fotografie ilustrativă, nu o măsurătoare.</span>@endif
                        </span>
                    </label>
                    @if ($t->requires_option)
                        @php($opts = $t->options->filter(fn ($op) => $op->is_active && $ot->allowsOption($op->code)))
                        <fieldset class="variants" data-option-for="{{ $t->id }}" style="margin-top: 12px; width: 100%">
                            <legend>Alege nuanța</legend>
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
