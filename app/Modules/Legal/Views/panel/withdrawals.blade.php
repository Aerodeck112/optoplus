@extends('layouts.panou')
@section('title', 'Retrageri din contract')
@section('content')
    <x-panou.page-head title="Retrageri din contract" sub="Declarațiile trimise de clienți prin „Retrageți-vă din contract aici”. Clientul a primit automat confirmarea pe email. Dacă produsul are drept de retragere, banii se restituie în cel mult 14 zile de la data declarației." />
    <x-panou.panel :flush="true">
        <form class="filters" method="get">
            <x-panou.field name="stare" type="select" label="Stare" :options="\App\Modules\Legal\Models\ContractWithdrawal::STATUSES" :value="$status" placeholder="toate" />
            <button class="btn" type="submit">Filtrează</button>
        </form>
        @if ($items->isEmpty())
            <x-panou.empty title="Nicio declarație de retragere">Când un client se retrage online dintr-o comandă, declarația apare aici și pe email.</x-panou.empty>
        @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Primită</th><th>Comanda</th><th>Client</th><th>Mesaj</th><th>Stare</th></tr></thead>
            <tbody>@foreach ($items as $w)
                <tr>
                    <td class="num">{{ $w->submitted_at->format('d.m.Y H:i') }}<br><span class="small muted">confirmare: {{ $w->confirmation_sent_at ? 'trimisă' : 'NETRIMISĂ' }}</span></td>
                    <td>@if($w->order)<a href="{{ route('panou.orders.show', $w->order->uuid) }}">{{ $w->order->number }}</a>@else{{ $w->order_reference }} <x-panou.badge tone="warn">negăsită</x-panou.badge>@endif</td>
                    <td>{{ $w->name }}<br><span class="small muted">{{ $w->email }}</span></td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($w->details, 160) }}</td>
                    <td>
                        @can('orders.manage')
                        <form method="post" action="{{ route('panou.legal.withdrawals.update', $w->id) }}" class="stack" style="gap:.3rem">@csrf
                            <x-panou.field :name="'stare'" type="select" label="Stare" :options="\App\Modules\Legal\Models\ContractWithdrawal::STATUSES" :value="$w->status" :placeholder="false" :id="'s'.$w->id" />
                            <x-panou.field :name="'nota'" label="Notă internă" :value="$w->staff_note" :id="'n'.$w->id" />
                            <button class="btn btn--small" type="submit">Salvează</button>
                        </form>
                        @else
                            {{ \App\Modules\Legal\Models\ContractWithdrawal::STATUSES[$w->status] ?? $w->status }}
                        @endcan
                        @if($w->handler)<span class="small muted">{{ $w->handler->name }}</span>@endif
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @endif
        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
