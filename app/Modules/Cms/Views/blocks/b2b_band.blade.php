<section class="section section--navy">
    <div class="wrap b2b-band">
        <div>
            @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
            @if (! empty($d['text']))<p class="lead" style="color: #d9e6ef">{{ $d['text'] }}</p>@endif
            <div class="btn-row" style="margin-top: 24px">
                @if (! empty($d['primary']['url']))<a class="btn btn--light" href="{{ $d['primary']['url'] }}">{{ $d['primary']['label'] }}</a>@endif
                @if (! empty($d['secondary']['url']))<a class="btn btn--ghost" style="color: #fff" href="{{ $d['secondary']['url'] }}">{{ $d['secondary']['label'] }}</a>@endif
            </div>
        </div>
        @if (! empty($d['bullets']))
            <ul>@foreach ($d['bullets'] as $b)<li><x-site.icon name="check" />{{ $b['text'] }}</li>@endforeach</ul>
        @endif
    </div>
</section>
