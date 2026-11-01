{{--
    Grosimea lentilei după indice, desenată pentru rețeta clientului (sau un exemplu). Estimare orientativă după
    indicele de refracție: grosimea reală depinde și de ramă, diametru, centrare și design.
--}}
@php
    $powers = [];
    foreach ([$input->od ?? null, $input->os ?? null] as $e) {
        if ($e && $e->sph !== null) {
            $cyl = min(0, (int) ($e->cyl ?? 0));
            $powers[] = abs($e->sph + $cyl) >= abs($e->sph) ? $e->sph + $cyl : $e->sph;
        }
    }
    $power = $powers ? collect($powers)->sortByDesc(fn ($p) => abs($p))->first() : -400;
    $example = ! $powers;
    $indices = collect($indices)->unique()->sort()->values();
    $abs = min(abs($power), 1000) / 100;
    $minus = $power <= 0;
@endphp
@if ($indices->count() > 1 && $abs > 0)
<section class="explain" data-module="lens-explainer" data-explainer="thickness" aria-labelledby="tx-title">
    <div class="explain__head">
        <h2 id="tx-title">Cât de groasă va fi lentila</h2>
        <span class="illustrative">Estimare orientativă</span>
    </div>
    <p class="small">{{ $example ? 'Exemplu pentru o rețetă de −4,00.' : 'Pentru rețeta ta ('.\App\Modules\Configurator\Support\EyeRx::format($power).', meridianul cel mai puternic).' }} {{ $minus ? 'La lentilele „minus”, marginea e partea groasă.' : 'La lentilele „plus”, centrul e partea groasă.' }}</p>
    <svg class="tx" viewBox="0 0 {{ 30 + $indices->count() * 120 }} 170" role="img" aria-label="Comparație orientativă a grosimii lentilei pentru indicii disponibili">
        @foreach ($indices as $i => $index)
            @php
                $n = $index / 1000;
                $rel = 0.5 / max(0.01, $n - 1);
                $thick = 4 + $abs * 3.4 * $rel;       // px, partea groasă
                $thin = 3;                             // px, partea subțire
                $x = 30 + $i * 120; $cx = $x + 40; $top = 30; $h = 100;
                $edge = $minus ? $thick : $thin; $center = $minus ? $thin : $thick;
                $path = sprintf('M%.1f %.1f Q%.1f %.1f %.1f %.1f L%.1f %.1f Q%.1f %.1f %.1f %.1f Z',
                    $cx - $edge / 2, $top, $cx - $center + $edge / 2, $top + $h / 2, $cx - $edge / 2, $top + $h,
                    $cx + $edge / 2, $top + $h, $cx + $center - $edge / 2, $top + $h / 2, $cx + $edge / 2, $top);
                $pct = (int) round(100 * $rel / (0.5 / 0.5));
            @endphp
            <g class="tx-lens" data-index="{{ $index }}">
                <rect x="{{ $x }}" y="14" width="80" height="140" rx="10" class="tx-card"/>
                <path d="{{ $path }}" class="tx-shape" style="--d: {{ $i * 120 }}ms"/>
                <text x="{{ $cx }}" y="146" text-anchor="middle" font-size="12" font-weight="600">indice {{ number_format($n, 2, ',', '') }}</text>
                <text x="{{ $cx }}" y="26" text-anchor="middle" font-size="10" class="tx-mm">{{ $index <= 1500 ? 'referință' : '≈ '.min(100, $pct).'%' }}</text>
            </g>
        @endforeach
    </svg>
    <p class="small muted">Procentele compară partea groasă a lentilei cu indicele 1,50, la aceeași rețetă și aceeași ramă. Specialistul confirmă alegerea la verificarea rețetei.</p>
</section>
@endif
