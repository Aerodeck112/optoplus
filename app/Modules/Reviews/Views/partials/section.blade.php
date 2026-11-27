{{--
    1.0.27: recenziile (publicate după moderare) și formularul fără cont.
    Parametri: $reviewsProduct (Product|null — null = despre magazin), $reviews (summary), $reviewsTitle.
    Informarea despre verificarea recenziilor: Directiva (UE) 2019/2161 (Omnibus), Legea 363/2007.
--}}
@php($rp = $reviewsProduct ?? null)
@php($formOpen = $errors->hasAny(['nota', 'mesaj', 'nume', 'email', 'acord', 'titlu']) || session('review_sent'))
<section class="reviews" id="recenzii" aria-labelledby="recenzii-titlu">
    <div class="reviews__head">
        <h2 id="recenzii-titlu">{{ $reviewsTitle }}</h2>
        @if ($reviews['count'] > 0)
            <p class="reviews__score"><x-reviews-stars :value="$reviews['average']" /> <strong>{{ number_format($reviews['average'], 1, ',', '') }}</strong> din 5 · {{ $reviews['count'] }} {{ $reviews['count'] === 1 ? 'recenzie' : 'recenzii' }}</p>
        @else
            <p class="reviews__score muted">Încă nu există recenzii. Spuneți-ne părerea dumneavoastră!</p>
        @endif
    </div>
    @if (session('review_sent'))<div class="notice notice--ok" role="status"><x-site.icon name="check" /><p>Mulțumim! Recenzia apare pe site după ce o verificăm.</p></div>@endif
    @if ($reviews['items']->isNotEmpty())
        <div class="reviews__list">
            @foreach ($reviews['items'] as $r)
                <article class="review">
                    <header class="review__head">
                        <x-reviews-stars :value="$r->rating" />
                        @if ($r->verified)<span class="review__verified"><x-site.icon name="check" :size="16" />Cumpărare verificată</span>@endif
                    </header>
                    <div translate="no" @if($r->locale !== app()->getLocale()) lang="{{ $r->locale }}" @endif>
                        @if ($r->title)<h3 class="review__title">{{ $r->title }}</h3>@endif
                        <p class="review__body">{!! nl2br(e($r->body)) !!}</p>
                    </div>
                    <p class="review__meta small muted"><span translate="no">{{ $r->displayName() }}</span> · {{ $r->created_at->format('d.m.Y') }}</p>
                    @if ($r->reply)
                        <div class="review__reply"><strong>Răspunsul Optoplus</strong><p translate="no" lang="ro">{!! nl2br(e($r->reply)) !!}</p></div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
    <details class="reviews__form box" @if($formOpen || $reviews['count'] === 0) open @endif>
        <summary><x-site.icon name="star" />{{ $rp ? 'Scrieți o recenzie despre această ramă' : 'Lăsați-ne o părere' }}</summary>
        @if ($errors->hasAny(['nota', 'mesaj', 'nume', 'email', 'acord', 'titlu']))
            <div class="notice notice--error" role="alert"><x-site.icon name="warn" /><ul>@foreach (['nota', 'mesaj', 'nume', 'email', 'acord', 'titlu'] as $f)@error($f)<li>{{ $message }}</li>@enderror @endforeach</ul></div>
        @endif
        <form method="post" action="/recenzii/" class="form" novalidate>
            @csrf
            @if ($rp)<input type="hidden" name="produs" value="{{ $rp->id }}">@endif
            <fieldset class="stars-input">
                <legend>Nota dumneavoastră</legend>
                <div class="stars-input__row">
                    @foreach ([5, 4, 3, 2, 1] as $n)
                        <input type="radio" id="nota-{{ $n }}" name="nota" value="{{ $n }}" @checked((int) old('nota') === $n) required>
                        <label for="nota-{{ $n }}" title="{{ $n }} {{ $n === 1 ? 'stea' : 'stele' }}"><x-site.icon name="star" :size="30" /><span class="sr-only">{{ $n }} {{ $n === 1 ? 'stea' : 'stele' }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <div class="field"><label for="rv-titlu">Titlu <span class="hint">(opțional)</span></label><input id="rv-titlu" name="titlu" maxlength="120" value="{{ old('titlu') }}"></div>
            <div class="field"><label for="rv-mesaj">{{ $rp ? 'Cum vi se pare rama?' : 'Cum a fost experiența cu Optoplus?' }}</label><textarea id="rv-mesaj" name="mesaj" rows="4" minlength="10" maxlength="2000" required>{{ old('mesaj') }}</textarea></div>
            <div class="grid-2">
                <div class="field"><label for="rv-nume">Numele</label><input id="rv-nume" name="nume" autocomplete="given-name" maxlength="80" required value="{{ old('nume') }}"><span class="hint">Pe site apare doar prenumele și inițiala numelui.</span></div>
                <div class="field"><label for="rv-email">Email <span class="hint">(opțional, nu se afișează)</span></label><input id="rv-email" type="email" name="email" autocomplete="email" maxlength="190" value="{{ old('email') }}"><span class="hint">Dacă este emailul cu care ați comandat, recenzia primește eticheta „Cumpărare verificată”.</span></div>
            </div>
            <div class="hp" aria-hidden="true"><label for="rv-website">Nu completați acest câmp</label><input id="rv-website" name="website" tabindex="-1" autocomplete="off"></div>
            <label class="check"><input type="checkbox" name="acord" value="1" required @checked(old('acord'))><span>Sunt de acord ca recenzia să fie publicată pe site (cu prenumele meu). Detalii în <a href="/politica-de-confidentialitate/">politica de confidențialitate</a>.</span></label>
            <button class="btn btn--primary" type="submit">Trimite recenzia</button>
        </form>
    </details>
    <p class="reviews__policy small muted"><strong>Cum verificăm recenziile:</strong> le citim înainte de publicare și nu publicăm spam, limbaj jignitor sau date personale; nu ștergem recenziile pentru că sunt negative. Cele marcate „Cumpărare verificată” sunt trimise de pe adresa de email a unei comenzi la noi; celelalte nu sunt verificate ca provenind de la cumpărători.</p>
</section>
