@props(['name', 'size' => 22, 'label' => null])
{{-- Pictograme liniare 24×24, grosime 1,75, aceeași familie vizuală pe tot siteul. --}}
<svg {{ $attributes->merge(['class' => 'ico']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" focusable="false" @endif>
@switch($name)
@case('search')<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>@break
@case('user')<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5"/>@break
@case('bag')<path d="M5.5 8h13l-1 12h-11z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>@break
@case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
@case('close')<path d="M6 6l12 12M18 6 6 18"/>@break
@case('phone')<path d="M6.5 4h3l1.5 4-2 1.3a10 10 0 0 0 5.7 5.7L16 13l4 1.5v3a2 2 0 0 1-2.2 2A16 16 0 0 1 4.5 6.2 2 2 0 0 1 6.5 4z"/>@break
@case('chevron-down')<path d="m6 9 6 6 6-6"/>@break
@case('chevron-right')<path d="m9 6 6 6-6 6"/>@break
@case('chevron-left')<path d="m15 6-6 6 6 6"/>@break
@case('check')<path d="m5 12.5 4.5 4.5L19 7.5"/>@break
@case('info')<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/>@break
@case('warn')<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4.5M12 17h.01"/>@break
@case('upload')<path d="M12 15V4M7.5 8.5 12 4l4.5 4.5"/><path d="M4.5 15v3.5A1.5 1.5 0 0 0 6 20h12a1.5 1.5 0 0 0 1.5-1.5V15"/>@break
@case('lens')<circle cx="12" cy="12" r="8.5"/><path d="M5 10.5h14M12 3.5v17" opacity=".35"/><circle cx="12" cy="10.5" r="1.4"/>@break
@case('frame')<circle cx="7" cy="13" r="3.8"/><circle cx="17" cy="13" r="3.8"/><path d="M10.8 12.3c.8-.6 1.6-.6 2.4 0M3.2 12 2 9.5M20.8 12 22 9.5"/>@break
@case('lab')<path d="M9.5 3.5h5M10.5 3.5v5.8L5.2 18.4A1.4 1.4 0 0 0 6.4 20.5h11.2a1.4 1.4 0 0 0 1.2-2.1l-5.3-9.1V3.5"/><path d="M7.6 14.5h8.8"/>@break
@case('truck')<path d="M3 6.5h11v9H3zM14 9.5h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>@break
@case('shield')<path d="M12 3.5 5 6.2v5.3c0 4.3 3 7.6 7 9 4-1.4 7-4.7 7-9V6.2z"/><path d="m9 12 2.2 2.2L15.5 10"/>@break
@case('clock')<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('minus')<path d="M5 12h14"/>@break
@case('trash')<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 12.5h9l1-12.5"/>@break
@case('file')<path d="M7 3.5h7l4 4V20.5H7z"/><path d="M14 3.5V8h4M9.5 12.5h5M9.5 16h5"/>@break
@case('building')<path d="M4.5 20.5V5.5l8-2v17M12.5 8.5l7 2v10M3 20.5h18M7.5 8.5v.01M7.5 12v.01M7.5 15.5v.01M15.5 13.5v.01M15.5 17v.01"/>@break
@case('chat')<path d="M4.5 5.5h15v10h-8.5L6.5 19.5v-4h-2z"/>@break
@case('repeat')<path d="M17 3.5 20 6.5l-3 3"/><path d="M4 11.5v-1a4 4 0 0 1 4-4h12M7 20.5l-3-3 3-3"/><path d="M20 12.5v1a4 4 0 0 1-4 4H4"/>@break
@case('eye')<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/>@break
@case('ruler')<path d="M3.5 15.5 15.5 3.5l5 5-12 12z"/><path d="m7.5 11.5 1.8 1.8M10.5 8.5l1.8 1.8M13.5 5.5l1.8 1.8"/>@break
@default<circle cx="12" cy="12" r="2"/>
@endswitch
</svg>
