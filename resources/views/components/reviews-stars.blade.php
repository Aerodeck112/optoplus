@props(['value' => 0])
{{-- 1.0.27: steluțele unei note (0–5, cu jumătăți), cu text pentru cititoarele de ecran. --}}
@php($v = max(0, min(5, (float) $value)))
<span class="stars" role="img" aria-label="{{ str_replace('.', ',', (string) round($v, 1)) }} din 5 stele" style="--p: {{ round($v / 5 * 100, 1) }}%"><span class="stars__bg" aria-hidden="true">★★★★★</span><span class="stars__fg" aria-hidden="true">★★★★★</span></span>
