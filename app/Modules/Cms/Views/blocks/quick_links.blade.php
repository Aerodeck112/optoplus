{{-- Scurtături colorate cu pictograme (livrare, laborator, specialist, plată, parteneri, ajutor). --}}
@php($items = array_values(array_filter($d['items'] ?? [], fn ($i) => ! empty($i['title']) && ! empty($i['url']))))
@if ($items)
<section class="quick">
    <div class="wrap">
        <ul class="quick__grid">
            @foreach ($items as $it)
                <li><a class="quick__item quick__item--{{ $it['color'] ?: 'azur' }}" href="{{ $it['url'] }}">
                    <span class="quick__icon"><x-site.icon :name="$it['icon'] ?: 'check'" :size="30" /></span>
                    <span class="quick__title">{{ $it['title'] }}</span>
                    @if (! empty($it['text']))<span class="quick__text">{{ $it['text'] }}</span>@endif
                </a></li>
            @endforeach
        </ul>
    </div>
</section>
@endif
