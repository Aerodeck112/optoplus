@php($a = $media[$d['compare_a'] ?? 0] ?? null)
@php($b = $media[$d['compare_b'] ?? 0] ?? null)
<section class="section">
    <div class="wrap story">
        <div>
            @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
            <div class="prose">{!! $d['text'] ?? '' !!}</div>
            @if (! empty($d['cta']['url']))<p><a class="btn" href="{{ $d['cta']['url'] }}">{{ $d['cta']['label'] }}</a></p>@endif
        </div>
        @if ($a && $b)
            <div class="compare">
                <figure><x-site.picture :media="$a" sizes="(min-width: 900px) 280px, 45vw" /><figcaption>{{ $d['compare_a_caption'] ?? '' }}</figcaption></figure>
                <figure><x-site.picture :media="$b" sizes="(min-width: 900px) 280px, 45vw" /><figcaption>{{ $d['compare_b_caption'] ?? '' }}</figcaption></figure>
                @if (! empty($d['compare_note']))<p class="compare__caption">{{ $d['compare_note'] }}</p>@endif
            </div>
        @endif
    </div>
</section>
