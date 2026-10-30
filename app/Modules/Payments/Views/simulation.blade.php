@extends('layouts.site')
@section('title', 'Simulator de plată')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap" style="max-width: 560px; padding: 48px 0">
    <div class="box">
        <p class="tag tag--warn">Mediu de test — nu se încasează bani</p>
        <h1>Simulator de plată</h1>
        <p>Comanda {{ $payment->order->number }} · {{ \App\Core\Support\Money::format($payment->amount) }}</p>
        <p class="small muted">Butoanele trimit o notificare semnată, ca un procesator real. Revenirea pe pagina comenzii nu marchează plata.</p>
        <form method="post" class="form">
            @csrf
            <button class="btn btn--primary" name="rezultat" value="plateste">Plătește (simulare)</button>
            <button class="btn" name="rezultat" value="refuza">Refuză plata (simulare)</button>
        </form>
    </div>
</div>
@endsection
