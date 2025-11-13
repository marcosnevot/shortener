@php use Illuminate\Support\Str; @endphp
@extends('panel.layout')

@section('content')
<section class="card">
  <header class="card-toolbar">
    <h1 class="h3">Crear nuevo enlace</h1>
    <div class="toolbar-right">
      <span class="tag">K-anon: {{ config('shortener.k_anon') }}</span>
      <span class="tag">Rate: {{ config('shortener.rate_limits.create_per_min') }}/min</span>
    </div>
  </header>

  <form method="post" action="{{ route('panel.store') }}" class="grid cols-1 md:cols-3" novalidate>
    @csrf
    <div class="form-field">
      <label class="label muted" for="url">Destino (https)</label>
      <input id="url" name="url" placeholder="https://destino..." required>
      @error('url')
        <p class="form-error">⚠ {{ $message }}</p>
      @enderror
    </div>

    <div class="form-field">
      <label class="label muted" for="expires_at">Expira</label>
      <input id="expires_at" name="expires_at" type="datetime-local" placeholder="Opcional">
    </div>

    <div class="form-field">
      <label class="label muted" for="max_clicks">Máx. clics</label>
      <div class="row">
        <input id="max_clicks" name="max_clicks" type="number" min="1" placeholder="Opcional">
        <button class="btn btn-primary" type="submit" title="Crear" data-loading>Crear</button>
      </div>
    </div>
  </form>
</section>

<section class="card">
  <header class="card-toolbar">
    <h2 class="h3">Enlaces</h2>
    <div class="toolbar-right muted">Total: {{ $links->total() }}</div>
  </header>

  <div class="table-wrap">
    <table class="table" role="table">
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Short</th>
          <th scope="col">Destino</th>
          <th scope="col">Clicks</th>
          <th scope="col">Estado</th>
          <th scope="col" class="right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($links as $l)
          <tr>
            <td>#{{ $l->id }}</td>
            <td class="short-cell">
              <a class="link break-all" href="{{ url('/r/'.$l->slug) }}" target="_blank" rel="noopener">
                {{ url('/r/'.$l->slug) }}
              </a>
              <button class="btn btn-ghost" type="button" onclick="copy('{{ url('/r/'.$l->slug) }}')" title="Copiar enlace corto">Copiar</button>
            </td>
            <td class="muted">{{ Str::limit($l->url, 70) }}</td>
            <td>{{ $l->clicks_count }}</td>
            <td>
              @if($l->is_banned)
                <span class="pill bad">BANEADO</span>
              @elseif($l->deleted_at)
                <span class="pill warn">ELIMINADO</span>
              @else
                <span class="pill ok">OK</span>
              @endif
            </td>
            <td class="right actions">
              <a class="btn" href="{{ route('panel.show',$l->id) }}">Detalle</a>

              <form method="post" action="{{ route('panel.ban',$l->id) }}" class="inline">
                @csrf
                <button class="btn" title="Banear" data-loading>Ban</button>
              </form>

              <form method="post" action="{{ route('panel.destroy',$l->id) }}" class="inline" onsubmit="return confirm('¿Eliminar enlace?')">
                @csrf @method('delete')
                <button class="btn btn-danger" title="Eliminar" data-loading>Eliminar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted center">Sin enlaces todavía.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination-wrap">
    {{ $links->links() }}
  </div>
</section>
@endsection
