@extends('configurator::partials.shell')
@section('step')
<form method="post" action="/configurator/{{ $cfg->uuid }}/pas/4/" class="form">
    @csrf
    <h1>Tratamente</h1>
    @php($available = $offering?->treatments->filter(fn ($t) => $t->treatment?->is_active && $t->status !== 'inactiv')->sortBy(fn ($t) => $t->treatment->sort) ?? collect())
    @if ($available->isEmpty())
        <p>Lentila aleasă (<strong>{{ $offering?->product->name }}</strong>) nu are tratamente opționale. Poți continua la rezumat sau alege altă lentilă.</p>
    @else
        <p>Tratamentele sunt opționale. Îți spunem ce fac și ce nu fac, ca să alegi doar ce îți folosește.</p>
        @php($chosen = collect($cfg->treatments ?? [])->keyBy('treatment_id'))
        @php($blocked = $blockedTreatments ?? [])
        {{-- 1.0.16: toate fotocromaticele într-un singur card, cu variantele lui (se poate alege cel mult una). --}}
        @php($isPhoto = fn ($ot) => str_starts_with((string) $ot->treatment->kind, 'fotocromatic'))
        @php($photo = $available->filter($isPhoto)->values())
        @php($others = $available->reject($isPhoto)->values())
        <div class="choice-grid">
            @foreach ($others as $ot)
                @php($t = $ot->treatment)
                @php($why = $blocked[$t->id] ?? null)
                <div class="choice tx-card @if($why) choice--blocked @endif" @unless($why) data-tx-card @endunless>
                    <label class="check" style="flex: 1">
                        <input type="checkbox" name="tratamente[]" value="{{ $t->id }}" @checked(! $why && $chosen->has($t->id)) @disabled($why) data-calc data-treatment="{{ $t->id }}" @if($t->requires_option) data-needs-option @endif>
                        <span class="choice__body">
                            <span class="choice__title"><span>{{ $t->name }}</span><span class="tx-price">@if($ot->is_included) inclus @else +{{ \App\Core\Support\Money::format($ot->price) }} @endif @unless($why)<span class="tx-state" aria-hidden="true"><span class="tx-state__off">Adaugă</span><span class="tx-state__on">✓ Adăugat</span></span>@endunless</span></span>
                            <span class="choice__text"><strong>Ce face:</strong> {{ $t->benefits }}</span>
                            <span class="choice__text"><strong>Limite:</strong> {{ $t->limits }}</span>
                            @if ($why)<span class="choice__blocked"><strong>Nu este disponibil pentru alegerea ta:</strong> {{ $why }}</span>@endif
                        </span>
                    </label>
                    @unless ($why)
                    @include('configurator::partials.treatment-explainer', ['kind' => $t->kind, 'treatmentId' => $t->id])
                    @endunless
                    @include('configurator::partials.treatment-options', ['t' => $t, 'ot' => $ot, 'why' => $why, 'chosen' => $chosen])
                </div>
            @endforeach

            @if ($photo->isNotEmpty())
                @php($prices = $photo->map(fn ($ot) => $ot->is_included ? 0 : (int) $ot->price)->unique()->values())
                @php($allBlocked = $photo->every(fn ($ot) => isset($blocked[$ot->treatment_id])))
                @php($tagline = ['transitions' => 'Pentru fiecare zi', 'transitions-xtractive' => 'Și pentru condus', 'photofast' => 'Culori la modă', 'fotocromatic' => 'Varianta simplă'])
                @php($maxDark = ['transitions' => 80, 'fotocromatic' => 60])
                <div class="choice photo-group @if($allBlocked) choice--blocked @endif" data-photo-group>
                    <div class="photo-group__head">
                        <span class="choice__title"><span>Lentile fotocromatice</span><span class="tx-price">@if ($prices->count() === 1) +{{ \App\Core\Support\Money::format($prices[0]) }} @else de la +{{ \App\Core\Support\Money::format($prices->min()) }} @endif</span></span>
                        <span class="choice__text">Se închid la culoare afară, la soare, și redevin aproape transparente în interior: o singură pereche pentru interior și exterior.</span>
                        @if ($allBlocked)
                            <span class="choice__blocked"><strong>Nu este disponibil pentru alegerea ta:</strong> {{ $blocked[$photo[0]->treatment_id] }}</span>
                        @elseif ($photo->count() > 1)
                            <span class="choice__text photo-group__pick">Alege una dintre variante (sau niciuna):</span>
                        @endif
                    </div>
                    <div class="photo-variants" role="group" aria-label="Variante de lentile fotocromatice">
                        @foreach ($photo as $ot)
                            @php($t = $ot->treatment)
                            @php($why = $blocked[$t->id] ?? null)
                            <div class="photo-variant tx-card @if($why) choice--blocked @endif" @unless($why) data-tx-card @endunless>
                                <label class="check">
                                    <input type="checkbox" name="tratamente[]" value="{{ $t->id }}" @checked(! $why && $chosen->has($t->id)) @disabled($why) data-calc data-treatment="{{ $t->id }}" @if($t->requires_option) data-needs-option @endif data-exclusive="fotocromatic" data-photo-variant="{{ $t->kind === 'fotocromatic_condus' ? 'xtractive' : 'helio' }}" @isset($maxDark[$t->code]) data-photo-max="{{ $maxDark[$t->code] }}" @endisset>
                                    <span class="choice__body">
                                        @isset($tagline[$t->code])<span class="photo-variant__tag">{{ $tagline[$t->code] }}</span>@endisset
                                        <span class="choice__title"><span>{{ trim(preg_replace(['/^Fotocromatic\s+/u', '/\s*\(și pentru condus\)$/u'], '', $t->name)) ?: $t->name }}</span><span class="tx-price">@if ($prices->count() > 1)@if($ot->is_included) inclus @else +{{ \App\Core\Support\Money::format($ot->price) }} @endif @endif @unless($why)<span class="tx-state" aria-hidden="true"><span class="tx-state__off">Alege</span><span class="tx-state__on">✓ Ales</span></span>@endunless</span></span>
                                        <span class="choice__text">{{ $t->benefits }}</span>
                                        @if ($why && ! $allBlocked)<span class="choice__blocked"><strong>Nu este disponibil pentru alegerea ta:</strong> {{ $why }}</span>@endif
                                    </span>
                                </label>
                                @include('configurator::partials.treatment-options', ['t' => $t, 'ot' => $ot, 'why' => $why, 'chosen' => $chosen])
                                @if ($t->limits)
                                    <details class="photo-variant__limits"><summary>Limite</summary><p class="small">{{ $t->limits }}</p>@if ($t->brand_note)<p class="small muted">{{ $t->brand_note }}</p>@endif</details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @unless ($allBlocked)
                        @include('configurator::partials.treatment-explainer', ['kind' => 'fotocromatic', 'treatmentId' => null, 'group' => true])
                    @endunless
                    @if (! empty($photoElsewhere))
                        <ul class="photo-group__elsewhere small muted">
                            @foreach ($photoElsewhere as $other)
                                <li><span>{{ $other['name'] }}: se poate alege cu lentilele cu indicele {{ $other['indices'] }}.</span> <a href="{{ $cfg->stepUrl(3) }}">Schimbă lentila</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
        <div class="notice notice--error" role="alert" data-calc-errors hidden></div>
    @endif
    <div class="cfg__nav">
        <a class="btn btn--ghost" href="{{ $cfg->stepUrl(3) }}">Înapoi la lentile</a>
        <button class="btn btn--primary" type="submit">Vezi rezumatul</button>
    </div>
</form>
@endsection
