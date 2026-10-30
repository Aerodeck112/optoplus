@extends('b2b::portal.shell')
@section('title', 'Import CSV')
@section('heading', 'Import din CSV')
@section('body')
<div class="layout-2">
    <div>
        @isset($report)
            <section class="box">
                <h2>Raport: {{ $report->original_name }}</h2>
                <p>{{ $report->rows_valid }} din {{ $report->rows_total }} rânduri sunt corecte. {{ $report->status === 'adaugat_in_cos' ? 'Au fost adăugate în coș.' : 'Nu s-a adăugat nimic în coș.' }}</p>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Rând</th><th>Cod pacient</th><th>Rezultat</th></tr></thead>
                    <tbody>@foreach ($report->report as $r)<tr><td>{{ $r['rand'] }}</td><td>{{ $r['cod_pacient'] ?? '—' }}</td><td>@if($r['ok'])<span class="ok">corect</span>@else<span class="error-text">{{ implode(' ', $r['erori']) }}</span>@endif @foreach ($r['avertismente'] as $w)<br><small class="muted">{{ $w }}</small>@endforeach</td></tr>@endforeach</tbody>
                </table></div>
            </section>
        @endisset
        <form method="post" action="/portal/import/" enctype="multipart/form-data" class="box form">
            @csrf
            <h2>Încarcă fișierul</h2>
            <div class="field"><label for="fisier">Fișier CSV (separator „;” sau „,”, maximum 200 de rânduri)</label><input id="fisier" type="file" name="fisier" accept=".csv,text/csv" required></div>
            <button class="btn btn--primary" type="submit">Verifică fișierul</button>
            <p class="small"><a href="/portal/import/model.csv">Descarcă modelul CSV</a> · coloane: <code>{{ implode(';', \App\Modules\B2B\Services\QuickOrder::COLUMNS) }}</code></p>
        </form>
    </div>
    <aside class="box">
        <h2>Importuri recente</h2>
        @forelse ($imports as $i)<p class="small"><a href="/portal/import/{{ $i->id }}/">{{ $i->original_name }}</a><br><span class="muted">{{ $i->created_at->format('d.m.Y H:i') }} · {{ $i->rows_valid }}/{{ $i->rows_total }} corecte</span></p>@empty<p class="small muted">Niciun import.</p>@endforelse
    </aside>
</div>
@endsection
