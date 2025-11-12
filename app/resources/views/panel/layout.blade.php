<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Shortener — Panel</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{
      --bg:#0b0b0c;
      --bg2:#0f0f12;
      --surface:#131317;
      --elev:#191a20;
      --muted:#a8a8b3;
      --text:#e7e7ee;
      --primary:#63a1ff;
      --primary-600:#438eff;
      --danger:#ff5b6b;
      --warn:#ffb454;
      --ok:#1dd1a1;
      --border:#262730;
      --focus: 0 0 0 3px rgba(99,161,255,.25);
      --radius:14px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;background:
        radial-gradient(1200px 600px at -10% -20%, #14202f 0%, transparent 60%),
        radial-gradient(1000px 600px at 120% 20%, #1a142f 0%, transparent 50%),
        var(--bg);
      color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;
    }
    header{
      position:sticky;top:0;z-index:50;
      backdrop-filter:saturate(1.2) blur(8px);
      background:linear-gradient(180deg, rgba(15,16,20,.85), rgba(15,16,20,.65));
      border-bottom:1px solid var(--border);
    }
    .wrap{max-width:1080px;margin:0 auto;padding:14px 18px}
    .bar{display:flex;align-items:center;gap:14px}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.3px}
    .logo-badge{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;
      background:linear-gradient(135deg,#2a3350,#111826); border:1px solid #2b2f3a}
    .logo svg{width:16px;height:16px;color:var(--primary)}

    main{max-width:1080px;margin:18px auto;padding:0 18px;display:grid;gap:18px}
    .card{background:linear-gradient(180deg,var(--surface),var(--elev));border:1px solid var(--border);
      border-radius:var(--radius); padding:16px}
    .card h3{margin:0 0 10px 0;font-size:16px}
    .grid{display:grid;gap:10px}
    @media (min-width:700px){ .grid.cols-3{grid-template-columns:1.6fr .9fr .7fr} }

    input,button,select{
      background:var(--bg2); color:var(--text); border:1px solid var(--border);
      border-radius:10px; padding:10px 12px; outline:none; transition:.2s border,.2s transform,.2s box-shadow;
    }
    input:focus,select:focus{box-shadow:var(--focus); border-color:#35518a}
    button{cursor:pointer}
    .btn{background:linear-gradient(180deg,#0f1724,#0c131e);border:1px solid #233146}
    .btn:hover{transform:translateY(-1px); border-color:#2e4a79}
    .btn-primary{background:linear-gradient(180deg,#0f2a54,#0d1e3a); border:1px solid #2b4b84}
    .btn-primary:hover{border-color:var(--primary-600)}
    .btn-ghost{background:transparent}
    .btn-danger{background:linear-gradient(180deg,#2b1216,#210d10); border:1px solid #4a2329; color:#ffdfe2}
    .btn-danger:hover{border-color:#6a2e36}

    .muted{color:var(--muted)}
    .right{text-align:right}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .row>*{flex:1}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:12px;border:1px solid var(--border);background:var(--bg2)}
    .pill.ok{border-color:#125e4b;background:#0f2b23;color:#99f1d9}
    .pill.warn{border-color:#5f481b;background:#2c2210;color:#ffd79a}
    .pill.bad{border-color:#5a1c24;background:#2b1216;color:#ffb7c0}

    table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid var(--border);border-radius:12px}
    thead th{position:sticky;top:0;background:#12131a;border-bottom:1px solid var(--border);text-align:left;padding:10px;font-weight:600}
    tbody td{padding:10px;border-bottom:1px solid #1a1b24; vertical-align:top}
    tbody tr:hover{background:#12141c}
    .actions form{display:inline}

    .toast{position:fixed;right:16px;bottom:16px;z-index:100;background:#0e3b1f;border:1px solid #1e7a3d;color:#c6ffe7;
      border-radius:12px;padding:12px 14px;box-shadow:0 6px 24px rgba(0,0,0,.35);opacity:0;transform:translateY(8px);transition:.2s}
    .toast.show{opacity:1;transform:none}

    .kbd{border:1px solid #3a3b45;border-bottom-width:2px;border-radius:6px;padding:0 6px;background:#0c0d11;color:#cdd1da;font-size:12px}
    .link{color:var(--primary);text-decoration:none}
    .link:hover{text-decoration:underline}

    /* Donut progress (detalle) */
    .donut{--p:0; --size:120px; width:var(--size); height:var(--size); border-radius:50%;
      background:conic-gradient(var(--primary) calc(var(--p)*1%), #23242c 0); display:grid; place-items:center;position:relative}
    .donut::after{content:""; position:absolute; width:calc(var(--size) - 24px); height:calc(var(--size) - 24px);
      background:var(--surface); border-radius:50%}
    .donut span{position:relative; z-index:1; font-weight:700}

    .tag{display:inline-block;padding:4px 8px;border:1px solid var(--border);border-radius:8px;background:#0e0f14;color:#b6bbcc;font-size:12px}
    .toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px}
    .toolbar .right{display:flex;gap:8px}
  </style>
</head>
<body>
  <header>
    <div class="wrap">
      <div class="bar">
        <div class="logo">
          <div class="logo-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 12h10M14 9l3 3-3 3M10 9l-3 3 3 3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          Shortener <span class="muted">Panel</span>
        </div>
        <span class="muted" style="margin-left:auto">Tema: Oscuro</span>
      </div>
    </div>
  </header>

  <main>
    @if(session('ok')) <div id="toast" class="toast show">{{ session('ok') }}</div> @endif
    @yield('content')
  </main>

  <script>
    function copy(txt){
      navigator.clipboard.writeText(txt).then(()=>toast('Copiado al portapapeles'));
    }
    function toast(msg){
      let t=document.getElementById('toast');
      if(!t){ t=document.createElement('div'); t.id='toast'; t.className='toast'; document.body.appendChild(t); }
      t.textContent=msg; t.classList.add('show');
      setTimeout(()=>t.classList.remove('show'), 1800);
    }
  </script>
</body>
</html>
