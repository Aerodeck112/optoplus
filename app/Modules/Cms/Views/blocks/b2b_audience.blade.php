{{-- Pentru cine este portalul: carduri cu pictogramă, titlu și text. --}}
<section class="section section--white">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
        <div class="audience">
            @foreach ($d['items'] ?? [] as $i => $item)
                @continue(empty($item['title']))
                <article class="audience__card" style="--i: {{ $i }}">
                    <span class="audience__icon"><x-site.icon :name="$item['icon'] ?: 'building'" :size="26" /></span>
                    <h3>{{ $item['title'] }}</h3>
                    @if (! empty($item['text']))<p>{{ $item['text'] }}</p>@endif
                </article>
            @endforeach
        </div>
    </div>
</section>
