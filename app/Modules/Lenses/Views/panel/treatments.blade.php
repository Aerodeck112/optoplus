@extends('layouts.panou')
@section('title', 'Tratamente lentile')
@section('content')
    <x-panou.page-head title="Tratamente lentile" sub="Ce vede clientul la pasul „Tratamente”: numele, „Ce face” și „Limite”, nuanțele. Prețul se stabilește la fiecare lentilă.">
        <x-slot:actions>
            @can('lenses.edit')
                <a class="btn btn--primary" href="{{ route('panou.lenses.treatments.create') }}"><x-panou.icon name="plus" />Tratament nou</a>
            @endcan
        </x-slot:actions>
    </x-panou.page-head>
    <x-panou.panel :flush="true">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Tratament</th><th>Tip</th><th>Nuanțe</th><th>Pe câte lentile</th><th>Stare</th></tr></thead>
            <tbody>
            @foreach ($treatments as $t)
                <tr>
                    <td><a class="row-link" href="{{ route('panou.lenses.treatments.edit', $t->id) }}">{{ $t->name }}</a></td>
                    <td>{{ $kinds[$t->kind] ?? $t->kind }}</td>
                    <td>
                        @if ($t->requires_option)
                            {{ $t->options->where('is_active', true)->pluck('label')->implode(', ') ?: '— niciuna activă —' }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="num">{{ $t->offerings_count }}</td>
                    <td>
                        @if ($t->is_active)
                            <x-panou.badge tone="ok">activ</x-panou.badge>
                        @else
                            <x-panou.badge>inactiv</x-panou.badge>
                        @endif
                        @unless ($t->is_confirmed)
                            <x-panou.badge tone="warn">neconfirmat</x-panou.badge>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </x-panou.panel>
@endsection
