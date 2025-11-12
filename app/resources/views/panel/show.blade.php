@extends('panel.layout')

@section('content')
@php
  $ratio = $link->max_clicks ? min(100, (int) round(($link->clicks_count / max(1,$link->max_clicks)) * 100)) : null;
@endphp

<div class="card">
  <div class="toolbar">
    <h3>Detalle enlace #{{ $link->id }}</h3>
    <div class="right"><a class="link" href="{{ route('panel.index') }}">← Volver</a></div>
  </div>

  <div class="row" style="align-items:center">
    <div style="flex:1.2">
      <p><strong>Short:</strong> <a class="link" target="_blank" href="{{ url('/r/'.$link->slug) }}">{{ url('/r/'.$link->slug) }}</a>
        <button class="btn btn-ghost" onclick="copy('{{ url('/r/'.$link->slug) }}')">Copiar</button>
      </p>
      <p><strong>Destino:</strong> <span class="muted">{{ $link->url }}</span></p>
      <p class="row">
        <span><strong>Clicks:</strong> {{ $link->clicks_count }}</span>
        <span><strong>Máx.:</strong> {{ $link->max_clicks ?? '—' }}</span>
        <span><strong>Expira:</strong> {{ $link->expires_at?->toDateTimeString() ?? '—' }}</span>
      </p>
      <p>
        <strong>Estado:</strong>
        @if($link->is_banned)
          <span class="pill bad">BANEADO</span>
        @elseif($link->deleted_at)
          <span class="pill warn">ELIMINADO</span>
        @else
          <span class="pill ok">OK</span>
        @endif
      </p>

      <div class="row">
        <form method="post" action="{{ route('panel.ban',$link->id) }}">@csrf
          <button class="btn" {{ $link->is_banned ? 'disabled' : '' }}>Banear</button>
        </form>
        <form method="post" action="{{ route('panel.destroy',$link->id) }}" onsubmit="return confirm('¿Eliminar enlace?')">
          @csrf @method('delete')
          <button class="btn btn-danger">Eliminar</button>
        </form>
      </div>
    </div>

    <div style="flex:.8;display:grid;place-items:center">
      @if(!is_null($ratio))
        <div class="donut" style="--p: {{ $ratio }}; --size: 140px">
          <span>{{ $ratio }}%</span>
        </div>
        <div class="muted" style="margin-top:8px">Uso de cupo</div>
      @else
        <div class="pill">Sin límite de clics</div>
      @endif
    </div>
  </div>
</div>
@endsection
