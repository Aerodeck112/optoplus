@extends('b2b::portal.shell')
@section('title', 'Comenzi')
@section('heading', 'Comenzile organizației')
@section('body')
<form method="get" class="inline-field" style="max-width: 520px; margin-bottom: 16px">
    <label class="sr-only" for="q">Caută</label><input id="q" name="q" value="{{ $term }}" placeholder="Număr comandă, referința dvs. sau cod pacient">
    <button class="btn btn--small" type="submit">Caută</button>
</form>
<div class="box">@include('b2b::portal.partials.orders-table', ['orders' => $orders])</div>
{{ $orders->links('storefront::partials.pagination') }}
@endsection
