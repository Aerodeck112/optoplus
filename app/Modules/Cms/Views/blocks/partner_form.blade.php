<section class="section section--white" id="cerere">
    <div class="wrap" style="max-width: 860px">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['text']))<p>{{ $d['text'] }}</p>@endif
        @include('storefront::forms.partner')
    </div>
</section>
