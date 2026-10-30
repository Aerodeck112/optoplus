<section class="section section--white">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
        <div class="photo-row">
            @foreach ($d['items'] ?? [] as $item)
                @if ($m = $media[$item['media'] ?? 0] ?? null)
                    <figure><x-site.picture :media="$m" sizes="(min-width: 900px) 280px, 45vw" /><figcaption>{{ $item['caption'] }}</figcaption></figure>
                @endif
            @endforeach
        </div>
    </div>
</section>
