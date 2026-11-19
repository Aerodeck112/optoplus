@props(['amount', 'from' => false, 'unit' => null, 'currency' => null])
<span {{ $attributes->merge(['class' => 'price']) }}>@if($from)<span class="price__from">de la</span> @endif{{ \App\Core\Support\Money::format($amount, true, $currency ?? app(\App\Modules\Regions\Services\Regions::class)->currency()) }}@if($unit)<span class="price__unit"> / {{ $unit }}</span>@endif</span>
