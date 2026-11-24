@props(['hex' => null])
{{-- 1.0.25: mostra de culoare a ramei (aleasă în panou); doar #RRGGBB validat. --}}
@if ($hex && preg_match('/^#[0-9a-f]{6}$/i', $hex))<span class="color-dot" style="--c: {{ $hex }}" aria-hidden="true"></span>@endif
