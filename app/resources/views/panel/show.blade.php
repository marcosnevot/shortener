@extends('panel.layout')

@section('content')
@php
  $ratio = $link->max_clicks ? min(100, (int) round(($link->clicks_count / max(1,$link->max_clicks)) * 100)) : null;
@endphp

<section class="card">
  <header class="card-toolbar">
    <h1 class="h3">Detalle enlace #{{ $link->id }}</h1>
    <div class="toolbar-right">
      <a class="link" href="{{ route('panel.index') }}" aria-label="Volver al listado">← Volver</a>
    </div>
  </header>

  <div class="row align-center wrap-gap">
    <div class="col flex-6">
      <p>
        <strong>Short:</strong>
        <a class="link break-all" target="_blank" rel="noopener" href="{{ url('/r/'.$link->slug) }}">
          {{ url('/r/'.$link->slug) }}
        </a>
        <button class="btn btn-ghost" type="button" onclick="copy('{{ url('/r/'.$link->slug) }}')">Copiar</button>
      </p>

      <p><strong>Destino:</strong> <span class="muted break-all">{{ $link->url }}</span></p>

      <p class="row wrap-gap">
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

      <div class="row wrap-gap">
        <form method="post" action="{{ route('panel.ban',$link->id) }}">
          @csrf
          <button class="btn" {{ $link->is_banned ? 'disabled' : '' }} data-loading>Banear</button>
        </form>

        <form method="post" action="{{ route('panel.destroy',$link->id) }}" onsubmit="return confirm('¿Eliminar enlace?')">
          @csrf @method('delete')
          <button class="btn btn-danger" data-loading>Eliminar</button>
        </form>
      </div>
    </div>

    <div class="col flex-4 center">
      @if(!is_null($ratio))
        <figure class="donut" style="--p: {{ $ratio }}; --size: 140px" aria-label="Uso de cupo {{ $ratio }}%">
          <figcaption>{{ $ratio }}%</figcaption>
        </figure>
        <div class="muted mt-2">Uso de cupo</div>
      @else
        <span class="pill">Sin límite de clics</span>
      @endif
    </div>
  </div>
</section>
@endsection
