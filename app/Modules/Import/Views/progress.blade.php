@extends('layouts.panou')
@section('title', 'Import în curs')
@section('content')
    <x-panou.page-head title="Import în curs" />
    <x-panou.panel>
        <p>{{ $run->cursor }} din {{ $run->total }} produse. Pagina continuă automat; nu o închideți.</p>
        <progress value="{{ $run->cursor }}" max="{{ max(1, $run->total) }}" style="width:100%"></progress>
        <form method="post" action="{{ route('panou.import.commit') }}" data-module="autosubmit">@csrf<input type="hidden" name="fisier" value="{{ $file }}"><button class="btn" type="submit">Continuă importul</button></form>
    </x-panou.panel>
@endsection
