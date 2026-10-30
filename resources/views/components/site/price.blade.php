@props(['amount', 'from' => false, 'unit' => null])
<span {{ $attributes->merge(['class' => 'price']) }}>@if($from)<span class="price__from">de la</span> @endif{{ \App\Core\Support\Money::format($amount) }}@if($unit)<span class="price__unit"> / {{ $unit }}</span>@endif</span>
