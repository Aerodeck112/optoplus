<section class="section section--white">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['text']))<p class="lead">{{ $d['text'] }}</p>@endif
        <div class="btn-row">
            @if (! empty($d['primary']['url']))<a class="btn btn--primary" href="{{ $d['primary']['url'] }}">{{ $d['primary']['label'] }}</a>@endif
            @if (! empty($d['secondary']['url']))<a class="btn" href="{{ $d['secondary']['url'] }}">{{ $d['secondary']['label'] }}</a>@endif
        </div>
    </div>
</section>
