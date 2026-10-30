<h2 id="rezumat-titlu">Prețul tău</h2>
@php($components = $quote->components())
@if ($components === [])
    <p class="muted">Prețul apare după ce alegi rama sau lentilele.</p>
@else
    <dl>
        @foreach ($components as $c)
            <dt>{{ $c->name }}<small>
                @switch($c->type)
                    @case('frame') rama @break
                    @case('lens') lentile · {{ $c->quantity }} {{ $c->unitLabel }} @break
                    @case('treatment') tratament · {{ $c->included ? 'inclus' : $c->unitLabel }} @break
                    @default {{ $c->quantity }} buc.
                @endswitch
            </small></dt>
            <dd>{{ \App\Core\Support\Money::format($c->amount()) }}</dd>
            @foreach ($c->discounts as $d)
                <dt class="discount"><small>{{ $d['label'] }}</small></dt><dd class="discount">−{{ \App\Core\Support\Money::format($d['amount']) }}</dd>
            @endforeach
        @endforeach
    </dl>
    <div class="summary__total"><span>Total</span><span data-total-inline>{{ \App\Core\Support\Money::format($quote->totalGross) }}</span></div>
    <p class="summary__note">Prețuri cu TVA. Transportul se calculează în coș, după alegerea livrării.</p>
@endif
@if ($validation->requiresReview())
    <p class="summary__note"><x-site.icon name="shield" :size="16" /> Un specialist optic verifică rețeta și lentila aleasă înainte de producție.</p>
@endif
