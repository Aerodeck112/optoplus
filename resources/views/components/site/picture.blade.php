@props(['media' => null, 'alt' => '', 'sizes' => '100vw', 'eager' => false, 'width' => null, 'height' => null, 'class' => ''])
{{-- Imagine responsivă din biblioteca media (variante WebP); încărcare întârziată doar sub prima ecranare. --}}
@if ($media)
    @php($srcset = $media->srcset())
    <img class="{{ $class }}" src="{{ $media->bestUrl(960) }}" @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
         alt="{{ $alt ?: $media->alt }}" width="{{ $width ?? $media->width }}" height="{{ $height ?? $media->height }}"
         @if($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async">
@else
    <span class="img-placeholder {{ $class }}" role="img" aria-label="{{ $alt ?: 'Imagine indisponibilă' }}"><x-site.icon name="frame" :size="48" /></span>
@endif
