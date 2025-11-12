@php use Illuminate\Support\Str; @endphp
@extends('panel.layout')

@section('content')
<div class="card">
  <div class="toolbar">
    <h3>Crear nuevo enlace</h3>
    <div class="right">
      <span class="tag">K-anon: {{ config('shortener.k_anon') }}</span>
      <span class="tag">Rate: {{ config('shortener.rate_limits.create_per_min') }}/min</span>
    </div>
  </div>

  <form method="post" action="{{ route('panel.store') }}" class="grid cols-3" novalidate>
    @csrf
    <div>
      <label class="muted">Destino (https) </label>
      <input name="url" placeholder="https://destino..." required>
      @error('url')<div class="muted" style="margin-top:6px;color:#ff8f98">⚠ {{ $message }}</div>@enderror
    </div>
    <div>
      <label class="muted">Expira</label>
      <input name="expires_at" type="datetime-local" placeholder="Opcional">
    </div>
    <div>
      <label class="muted">Máx. clics</label>
      <div class="row">
        <input name="max_clicks" type="number" min="1" placeholder="Opcional">
        <button class="btn btn-primary" type="submit" title="Crear">Crear</button>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div class="toolbar">
    <h3>Enlaces</h3>
    <div class="right muted">Total: {{ $links->total() }}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Short</th>
        <th>Destino</th>
        <th>Clicks</th>
        <th>Estado</th>
        <th class="right">Acciones</th>
      </tr>
    </thead>
    <tbody>
    @forelse($links as $l)
      <tr>
        <td>#{{ $l->id }}</td>
        <td>
          <a class="link" href="{{ url('/r/'.$l->slug) }}" target="_blank">{{ url('/r/'.$l->slug) }}</a>
          <button class="btn btn-ghost" onclick="copy('{{ url('/r/'.$l->slug) }}')" title="Copiar">Copiar</button>
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
          <form method="post" action="{{ route('panel.ban',$l->id) }}" style="display:inline">@csrf
            <button class="btn" title="Ban">Ban</button>
          </form>
          <form method="post" action="{{ route('panel.destroy',$l->id) }}" style="display:inline" onsubmit="return confirm('¿Eliminar enlace?')">
            @csrf @method('delete')
            <button class="btn btn-danger" title="Eliminar">Eliminar</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Sin enlaces todavía.</td></tr>
    @endforelse
    </tbody>
  </table>

  <div class="muted" style="margin-top:10px">{{ $links->links() }}</div>
</div>
@endsection
