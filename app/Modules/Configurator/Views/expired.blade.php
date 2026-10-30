@extends('layouts.site')
@section('title', 'Link expirat')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap section" style="padding-top: 32px">
    <h1>Linkul nu mai este valabil</h1>
    <p class="lead">Linkurile pentru reluarea configurației expiră după {{ config('optoplus.configurator.share_link_days') }} de zile sau după ce se generează unul nou.</p>
    <div class="btn-row"><a class="btn btn--primary" href="/configurator/">Începe o configurație nouă</a><a class="btn" href="/cont/autentificare/">Intră în cont</a></div>
</div>
@endsection
