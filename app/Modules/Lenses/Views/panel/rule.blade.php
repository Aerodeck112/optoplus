@extends('layouts.panou')
@section('title', $rule->exists ? $rule->name : 'Regulă nouă')
@section('content')
    <x-panou.page-head :title="$rule->exists ? $rule->name : 'Regulă nouă'" :crumbs="['Reguli' => route('panou.lenses.rules.index'), ($rule->exists ? $rule->name : 'nouă') => null]" />
    <div class="grid grid--aside">
        <x-panou.panel>
            <form method="post" action="{{ $rule->exists ? route('panou.lenses.rules.update', $rule->id) : route('panou.lenses.rules.store') }}" class="form-grid" data-module="rule-editor">@csrf
                <x-panou.field name="nume" label="Nume intern" :value="$rule->name" required :full="true" />
                <x-panou.field name="efect" type="select" label="Efect" :options="['block' => 'Blochează (nu se poate comanda)', 'require_review' => 'Cere verificarea specialistului', 'warn' => 'Avertizează clientul', 'require_field' => 'Cere o măsurătoare/câmp']" :value="$rule->effect" :placeholder="false" />
                <x-panou.field name="camp_cerut" label="Câmpul cerut (doar pentru „cere câmp”)" :value="$rule->required_field" hint="fitting_height, pd_monocular, add" />
                <x-panou.field name="conditii" type="textarea" label="Condiții (JSON): grupuri legate prin SAU, condiții în grup legate prin ȘI" :value="old('conditii', json_encode($rule->conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))" :full="true" rows="10" style="font-family:monospace" required />
                <x-panou.field name="mesaj_client" type="textarea" label="Mesajul pentru client (limbaj simplu)" :value="$rule->message_customer" :full="true" required />
                <x-panou.field name="mesaj_intern" type="textarea" label="Nota internă" :value="$rule->message_staff" :full="true" />
                <x-panou.field name="prioritate" type="number" label="Prioritate (mic = întâi)" :value="$rule->priority" required />
                <x-panou.field name="sursa" label="Sursa regulii" :value="$rule->source" hint="ex. „laborator, 11.2026”" />
                <x-panou.field name="activa" type="checkbox" label="Activă" :value="$rule->is_active" />
                <div class="form-actions">@can('lenses.edit')<button class="btn btn--primary" type="submit">Salvează</button>@endcan</div>
            </form>
        </x-panou.panel>
        <x-panou.panel title="Câmpuri și operatori">
            <p class="small">Exemplu: <code>[[{"field":"frame.rim_type","op":"in","value":["semi_nylon","rimless_screw"]},{"field":"index","op":"lt","value":1600}]]</code></p>
            <table class="table small"><tbody>@foreach ($fields as $code => $def)<tr><td><code>{{ $code }}</code></td><td>{{ $def['label'] }}@if($def['hint'] ?? null)<br><span class="muted">{{ $def['hint'] }}</span>@endif</td></tr>@endforeach</tbody></table>
            <p class="small">Operatori: @foreach ($operators as $op => $l)<code>{{ $op }}</code> ({{ $l }}) @endforeach</p>
        </x-panou.panel>
    </div>
@endsection
