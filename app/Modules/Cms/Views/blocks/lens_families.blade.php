@php($families = app(\App\Modules\Storefront\Services\LensShowcase::class)->families())
<section class="section section--white" id="lentile">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
        <div class="families">
            @foreach ($families as $f)
                <article class="family">
                    @include('storefront::partials.family-diagram', ['code' => $f['code']])
                    <h3>{{ $f['name'] }}</h3>
                    <p>{{ $f['description'] }}</p>
                    @if ($f['from'])<p class="family__price">pereche, de la {{ \App\Core\Support\Money::format($f['from']) }}</p>@endif
                    <a class="family__link" href="{{ $f['url'] }}">Vezi lentilele {{ mb_strtolower($f['name']) }}</a>
                </article>
            @endforeach
        </div>
        <p class="diagram-note">Schemele arată zonele de vedere ale fiecărei familii; sunt ilustrative, nu la scară.</p>
    </div>
</section>
