{{-- Categoriile principale, ca plăci mari cu fotografie. --}}
@php($items = array_values(array_filter($d['items'] ?? [], fn ($i) => ! empty($i['title']) && ! empty($i['url']))))
@if ($items)
<section class="section section--tiles">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        <ul class="tiles">
            @foreach ($items as $it)
                @php($img = $media[$it['image'] ?? 0] ?? null)
                <li><a class="tile tile--{{ $it['background'] ?: 'bleu' }}" href="{{ $it['url'] }}">
                    @if ($img)<span class="tile__img"><x-site.picture :media="$img" sizes="(min-width: 900px) 300px, 45vw" :alt="''" /></span>@endif
                    <span class="tile__title">{{ $it['title'] }}</span>
                </a></li>
            @endforeach
        </ul>
    </div>
</section>
@endif
