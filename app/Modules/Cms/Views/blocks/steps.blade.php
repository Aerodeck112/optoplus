<section class="section">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
        <ol class="steps" style="--n: {{ max(1, min(5, count($d['items'] ?? []))) }}">
            @foreach ($d['items'] ?? [] as $item)
                <li><h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></li>
            @endforeach
        </ol>
    </div>
</section>
