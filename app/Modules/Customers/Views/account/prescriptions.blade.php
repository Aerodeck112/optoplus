@extends('customers::account.shell')
@section('title', 'Rețetele mele')
@section('heading', 'Rețetele mele')
@php($f = fn ($v, $sign = true) => \App\Modules\Configurator\Support\EyeRx::format($v, $sign))
@section('body')
<p class="small muted">Rețetele sunt date privind sănătatea: le vedeți doar dumneavoastră și specialiștii Optoplus care lucrează la comenzi. Fiecare consultare se înregistrează.</p>
@forelse ($prescriptions as $p)
    <article class="box">
        <h2 class="h-reset">Rețeta din {{ $p->updated_at->format('d.m.Y') }}@if($p->verified_at) <span class="tag tag--stock">verificată de specialist</span>@endif</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AX</th><th>ADD</th></tr></thead>
            <tbody>
            @foreach (['od' => 'Ochiul drept (OD)', 'os' => 'Ochiul stâng (OS)'] as $eye => $label)
                @php($e = $p->eye($eye))
                <tr><th scope="row">{{ $label }}</th><td>{{ $e ? $f($e->sph) : '—' }}</td><td>{{ $e && $e->cyl ? $f($e->cyl) : '—' }}</td><td>{{ $e?->axis ?? '—' }}</td><td>{{ $e && $e->add ? $f($e->add, false) : '—' }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="small">@if($p->files_count){{ $p->files_count }} fișier(e) încărcate · @endif
            @if (in_array($p->id, $ordered, true)) folosită într-o comandă @else
                <form method="post" action="/cont/retete/{{ $p->id }}/sterge/" style="display:inline">@csrf<button class="link-btn" type="submit">Șterge rețeta</button></form>
            @endif
        </p>
    </article>
@empty
    <div class="empty"><p>Nu aveți rețete salvate. Rețeta se salvează când configurați ochelarii.</p></div>
@endforelse
@endsection
