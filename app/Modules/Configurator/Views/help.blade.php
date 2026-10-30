@extends('layouts.site')
@section('title', 'Am nevoie de ajutor cu lentilele')
@section('description', 'Trimite rețeta și spune-ne cum folosești ochelarii; un specialist Optoplus te contactează cu o recomandare.')
@section('content')
<div class="wrap section" style="padding-top: 24px; max-width: 900px">
    <x-site.breadcrumbs :items="['Configurează ochelarii' => '/configurator/', 'Am nevoie de ajutor' => null]" />
    <h1>Am nevoie de ajutor</h1>
    <p class="lead">Spune-ne cum folosești ochelarii și, dacă o ai, trimite rețeta. Un specialist te contactează, îți explică variantele și pregătește configurația; tu o confirmi înainte de orice plată.</p>
    <p class="muted">Nu punem diagnostice și nu modificăm rețeta. Dacă nu ai o rețetă recentă, programează un control la un medic oftalmolog sau la un optometrist.</p>
    @include('storefront::forms.help')
</div>
@endsection
