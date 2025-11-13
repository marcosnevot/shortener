<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Shortener — Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark light">
  <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
  <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
  <header class="site-header" role="banner">
    <div class="container">
      <div class="topbar">
        <a href="{{ route('panel.index') }}" class="brand" aria-label="Ir al panel">
          <span class="brand-badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="brand-icon">
              <path d="M7 12h10M14 9l3 3-3 3M10 9l-3 3 3 3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="brand-text">Shortener <span class="muted">Panel</span></span>
        </a>
      </div>
    </div>
  </header>

  <main id="content" class="site-main container" role="main">
    <div id="toast" class="toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>
    @yield('content')
  </main>

  <footer class="site-footer" role="contentinfo">
    <div class="container">
      <small class="muted">© {{ date('Y') }} URL Shortener - Marcos Nevot</small>
    </div>
  </footer>
</body>
</html>
