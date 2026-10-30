<section class="section" style="padding-top: 8px">
    <div class="wrap prose">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        {!! $d['html'] ?? '' !!}
    </div>
</section>
