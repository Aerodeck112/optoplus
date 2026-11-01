{{-- Pagina „mod lucru”: autonomă (fără meniuri), animată doar din CSS, fără resurse externe. --}}
<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Optoplus — noul magazin online este în lucru</title>
<meta name="robots" content="noindex">
<meta name="description" content="Noul magazin online Optoplus este în pregătire. Între timp ne găsiți la telefon și email.">
<meta name="theme-color" content="#053b5e">
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
:root{--navy:#053b5e;--navy-2:#032c47;--deep:#02223a;--glass:#cfe6f7;--amber:#e0a33a;--amber-2:#a15f0e;--ink:#eaf2f8;--mute:#9fbcd1}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{font-family:"Hanken Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:radial-gradient(1200px 700px at 75% -10%,#0b5583 0%,transparent 60%),radial-gradient(900px 600px at -10% 110%,#0a4a73 0%,transparent 55%),var(--deep);min-height:100vh;overflow-x:hidden}
.grid-bg{position:fixed;inset:0;background-image:linear-gradient(rgba(159,188,209,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(159,188,209,.07) 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(ellipse at center,#000 30%,transparent 75%);pointer-events:none}
.wrap{position:relative;max-width:1120px;margin:0 auto;padding:clamp(20px,4vw,48px) 20px 32px;display:grid;gap:32px;align-items:center;min-height:100vh}
@media(min-width:900px){.wrap{grid-template-columns:1.05fr .95fr}}
.logo{display:inline-flex;align-items:center;background:#fff;border-radius:14px;padding:10px 16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
.logo img{display:block;width:150px;height:auto}
.badge{display:flex;width:fit-content;align-items:center;gap:10px;margin:28px 0 14px;padding:8px 14px;border-radius:999px;background:rgba(224,163,58,.12);border:1px solid rgba(224,163,58,.45);color:#ffd9a0;font-weight:600;font-size:.92rem;letter-spacing:.02em}
.dot{width:9px;height:9px;border-radius:50%;background:var(--amber);box-shadow:0 0 0 0 rgba(224,163,58,.7);animation:ping 1.8s infinite}
h1{font-family:Spectral,Georgia,"Times New Roman",serif;font-weight:600;font-size:clamp(2.1rem,5vw,3.6rem);line-height:1.08;margin:0 0 16px;letter-spacing:-.01em}
h1 .hl{background:linear-gradient(90deg,#fff 0%,var(--glass) 40%,var(--amber) 60%,#fff 100%);background-size:250% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 6s linear infinite}
.lead{font-size:clamp(1.05rem,1.6vw,1.2rem);line-height:1.6;color:#cfe0ec;max-width:36em;margin:0 0 22px}
.lead strong{color:#fff}
.steps{list-style:none;margin:0 0 26px;padding:0;display:grid;gap:10px}
.steps li{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(159,188,209,.16);opacity:0;transform:translateY(8px);animation:rise .6s ease forwards}
.steps li:nth-child(1){animation-delay:.2s}.steps li:nth-child(2){animation-delay:.45s}.steps li:nth-child(3){animation-delay:.7s}.steps li:nth-child(4){animation-delay:.95s}
.ok{flex:none;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:#2e6b4f;color:#fff;font-size:.8rem;font-weight:700}
.busy{flex:none;width:24px;height:24px;border-radius:50%;border:3px solid rgba(224,163,58,.25);border-top-color:var(--amber);animation:spin 1s linear infinite}
.bar{height:10px;border-radius:99px;background:rgba(255,255,255,.08);overflow:hidden;margin:0 0 28px;max-width:440px;position:relative}
.bar span{position:absolute;inset:0;width:82%;border-radius:99px;background:linear-gradient(90deg,var(--navy),#1d7cb4,var(--amber));background-size:200% 100%;animation:flow 3s linear infinite}
.bar span::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);animation:sweep 2.2s ease-in-out infinite}
.contact{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:24px}
.card{display:flex;gap:12px;align-items:center;padding:14px 16px;border-radius:14px;background:#fff;color:var(--navy);text-decoration:none;box-shadow:0 12px 30px rgba(0,0,0,.22);transition:transform .2s ease}
.card:hover{transform:translateY(-2px)}
.card svg{flex:none}
.card small{display:block;color:#5c7183;font-size:.8rem}
.card strong{font-size:1.05rem}
.note{color:var(--mute);font-size:.95rem;margin:0 0 6px}
.made{margin-top:18px;color:var(--mute);font-size:.88rem}
.made a{color:#fff;font-weight:700;text-decoration:none;letter-spacing:.06em}
.made a:hover{text-decoration:underline}
.stage{position:relative;display:grid;place-items:center}
@media(max-width:899px){.stage svg{width:min(78%,340px)}.cap{display:none}.wrap{min-height:auto}}
.stage svg{width:min(100%,520px);height:auto;filter:drop-shadow(0 30px 60px rgba(0,0,0,.35))}
.frame{fill:none;stroke:#fff;stroke-width:9;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1400;stroke-dashoffset:1400;animation:draw 3.2s ease forwards .3s}
.lens{fill:url(#lensFill);opacity:0;animation:fade 1s ease forwards 2.4s}
.glint{fill:#fff;opacity:.0;animation:glint 4.5s ease-in-out infinite 3s}
.ring{fill:none;stroke:var(--glass);stroke-width:1.5;opacity:.0;transform-origin:center;transform-box:fill-box;animation:pulse 3.6s ease-out infinite}
.ring.r2{animation-delay:1.2s}.ring.r3{animation-delay:2.4s}
.tool{transform-origin:418px 382px;animation:work 1.6s ease-in-out infinite}
.spark{fill:var(--amber);opacity:0;animation:spark 1.6s ease-out infinite}
.spark.s2{animation-delay:.25s}.spark.s3{animation-delay:.5s}
.cap{position:absolute;bottom:4%;left:50%;transform:translateX(-50%);white-space:nowrap;font-size:.85rem;color:var(--mute);letter-spacing:.08em;text-transform:uppercase}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(224,163,58,.7)}70%{box-shadow:0 0 0 10px rgba(224,163,58,0)}100%{box-shadow:0 0 0 0 rgba(224,163,58,0)}}
@keyframes shine{to{background-position:-250% 0}}
@keyframes rise{to{opacity:1;transform:none}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes flow{to{background-position:-200% 0}}
@keyframes sweep{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
@keyframes draw{to{stroke-dashoffset:0}}
@keyframes fade{to{opacity:1}}
@keyframes glint{0%,100%{opacity:0;transform:translateX(-30px)}40%{opacity:.75}60%{opacity:0;transform:translateX(60px)}}
@keyframes pulse{0%{opacity:.6;transform:scale(.6)}100%{opacity:0;transform:scale(1.35)}}
@keyframes work{0%,100%{transform:rotate(-8deg)}50%{transform:rotate(10deg)}}
@keyframes spark{0%{opacity:0;transform:translate(0,0)}15%{opacity:1}100%{opacity:0;transform:translate(26px,-34px)}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}.frame{stroke-dashoffset:0}.lens,.steps li{opacity:1;transform:none}}
</style>
</head>
<body>
<div class="grid-bg" aria-hidden="true"></div>
<main class="wrap">
    <section>
        <span class="logo"><img src="/images/optoplus-logo.webp" alt="Optoplus — Expertul vederii" width="150" height="51"></span>
        <p class="badge"><span class="dot" aria-hidden="true"></span>Magazinul online este în lucru</p>
        <h1>Noul magazin <span class="hl">Optoplus</span> prinde contur</h1>
        <p class="lead">Echipa <strong>VITIM</strong> lucrează chiar acum la magazinul online: rame alese pentru tine și <strong>lentile produse în laboratorul Optoplus</strong>, configurate pas cu pas, după rețeta ta.</p>
        @if ($message)<p class="lead">{{ $message }}</p>@endif
        <ul class="steps" aria-label="Ce pregătim">
            <li><span class="ok" aria-hidden="true">✓</span>Catalogul de rame și ochelari</li>
            <li><span class="ok" aria-hidden="true">✓</span>Lentilele produse în laboratorul nostru</li>
            <li><span class="ok" aria-hidden="true">✓</span>Configuratorul de ochelari, pas cu pas</li>
            <li><span class="busy" aria-hidden="true"></span>Ultimele verificări înainte de lansare</li>
        </ul>
        <div class="bar" role="img" aria-label="Lucrările sunt aproape gata"><span></span></div>
        @if ($launch)<p class="note">Lansarea estimată: <strong style="color:#fff">{{ $launch }}</strong></p>@endif
        <p class="note">Până atunci suntem aici pentru tine — întrebări despre rame, lentile sau comenzi:</p>
        <div class="contact">
            @if ($phone)<a class="card" href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 4h3l1.5 4-2 1.3a10 10 0 0 0 5.7 5.7L16 13l4 1.5v3a2 2 0 0 1-2.2 2A16 16 0 0 1 4.5 6.2 2 2 0 0 1 6.5 4z"/></svg><span><small>Sună-ne</small><strong>{{ $phone }}</strong></span></a>@endif
            @if ($email)<a class="card" href="mailto:{{ $email }}"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span><small>Scrie-ne</small><strong>{{ $email }}</strong></span></a>@endif
        </div>
        @if ($address)<p class="note">{{ $address }}@if($hours) · {{ $hours }}@endif</p>@endif
        <p class="made">Magazin online realizat de <a href="https://vitim.ro" rel="noopener" target="_blank">VITIM</a></p>
    </section>
    <section class="stage" aria-hidden="true">
        <svg viewBox="0 0 520 460">
            <defs>
                <radialGradient id="lensFill" cx="40%" cy="35%" r="70%"><stop offset="0" stop-color="#e9f5ff" stop-opacity=".95"/><stop offset=".6" stop-color="#9ccbea" stop-opacity=".55"/><stop offset="1" stop-color="#3f86b6" stop-opacity=".5"/></radialGradient>
                <clipPath id="clipL"><path d="M70 170c0-40 28-60 82-60s88 18 88 62-26 88-88 88-82-42-82-90z"/></clipPath>
                <clipPath id="clipR"><path d="M280 172c0-44 34-62 88-62s82 20 82 60-20 90-82 90-88-44-88-88z"/></clipPath>
            </defs>
            <circle class="ring" cx="155" cy="182" r="96"/><circle class="ring r2" cx="155" cy="182" r="96"/><circle class="ring r3" cx="365" cy="182" r="96"/>
            <path class="lens" d="M70 170c0-40 28-60 82-60s88 18 88 62-26 88-88 88-82-42-82-90z"/>
            <path class="lens" d="M280 172c0-44 34-62 88-62s82 20 82 60-20 90-82 90-88-44-88-88z"/>
            <g clip-path="url(#clipL)"><rect class="glint" x="60" y="100" width="34" height="180" transform="skewX(-20)"/></g>
            <g clip-path="url(#clipR)"><rect class="glint" x="270" y="100" width="34" height="180" transform="skewX(-20)"/></g>
            <path class="frame" d="M70 170c0-40 28-60 82-60s88 18 88 62-26 88-88 88-82-42-82-90z M280 172c0-44 34-62 88-62s82 20 82 60-20 90-82 90-88-44-88-88z M240 168c12-14 28-14 40 0 M70 160 20 140 M450 160 500 140"/>
            <g class="tool"><rect x="398" y="330" width="16" height="96" rx="6" fill="#e0a33a" transform="rotate(-35 406 378)"/><rect x="420" y="300" width="12" height="40" rx="3" fill="#cfe6f7" transform="rotate(-35 426 320)"/></g>
            <circle class="spark" cx="392" cy="300" r="3.5"/><circle class="spark s2" cx="398" cy="304" r="2.5"/><circle class="spark s3" cx="388" cy="298" r="3"/>
        </svg>
        <span class="cap">Lentile în pregătire</span>
    </section>
</main>
</body>
</html>
