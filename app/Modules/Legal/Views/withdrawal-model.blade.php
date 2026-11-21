@extends('layouts.site')
@section('title', 'Formular tip de retragere')
@section('content')
@php($s = app(\App\Core\Settings\Settings::class))
<div class="wrap page-pad legal-flow">
    <h1>Formular tip de retragere</h1>
    <p class="lead">Completați și trimiteți acest formular numai dacă doriți să vă retrageți din contract. Cel mai simplu este <a href="/retragere/">online, aici</a>; puteți trimite însă și acest formular pe email sau prin poștă.</p>
    <div class="box legal-model" translate="no" lang="ro">
        <p><strong>Către:</strong> {{ $s->get('company.name') }}, {{ $s->get('company.address') }}, email {{ $s->get('contact.email_b2c') }}@if($s->get('contact.phone_b2c')), telefon {{ $s->get('contact.phone_b2c') }}@endif</p>
        <p>Prin prezenta vă informez/informăm (*) cu privire la retragerea mea/noastră (*) din contractul referitor la vânzarea următoarelor produse (*):</p>
        <p class="legal-model__line">………………………………………………………………………………………………</p>
        <p>Comandate la data (*) / primite la data (*): ……………………… · Numărul comenzii: ………………………</p>
        <p>Numele consumatorului (consumatorilor): ………………………………………………</p>
        <p>Adresa consumatorului (consumatorilor): ………………………………………………</p>
        <p>Semnătura consumatorului (consumatorilor) (numai în cazul în care acest formular este notificat pe hârtie): ………………</p>
        <p>Data: ………………………</p>
        <p class="small muted">(*) Se elimină mențiunea inutilă. Formular conform anexei nr. 1 lit. B din OUG nr. 34/2014.</p>
    </div>
    <p><button class="btn btn--ghost" type="button" data-module="print">Tipărește formularul</button></p>
</div>
@endsection
