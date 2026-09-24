<x-filament-panels::page>
    @php
        $mes = \Illuminate\Support\Carbon::parse($this->mes . '-01');
        $supervisores = $this->supervisores;
        $dias = $this->dias;
        $mapa = $this->mapaVisitas;
    @endphp

    <x-filament::section>
        <div class="visitas-toolbar">
            <div class="visitas-periodo">
                <x-filament::button wire:click="mesAnterior" icon="heroicon-m-chevron-left" color="gray" size="sm" aria-label="Mes anterior" />
                <div>
                    <p>{{ $mes->translatedFormat('F Y') }}</p>
                </div>
                <x-filament::button wire:click="mesSiguiente" icon="heroicon-m-chevron-right" color="gray" size="sm" aria-label="Mes siguiente" />
                <x-filament::button wire:click="irAHoy" color="gray" size="sm">Hoy</x-filament::button>
            </div>

            <div class="visitas-filtros">
                <x-filament::input.select wire:model.live="supervisorId" aria-label="Filtrar por supervisora">
                    <option value="">Todas las supervisoras</option>
                    @foreach ($this->supervisores as $supervisora)
                        <option value="{{ $supervisora->id }}">{{ $supervisora->name }}</option>
                    @endforeach
                </x-filament::input.select>

                <x-filament::input.select wire:model.live="sucursalId" aria-label="Filtrar por local">
                    <option value="">Todos los locales</option>
                    @foreach ($this->sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </x-filament::input.select>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div class="visitas-calendario-scroll" x-init="$el.scrollLeft = 0" role="region" aria-label="Calendario de visitas de supervisión" tabindex="0">
            <div class="visitas-calendario" style="--dias: {{ count($dias) }}">
                <div class="visitas-esquina">Supervisora</div>
                @foreach ($dias as $dia)
                    <div @class(['visitas-dia-cabecera', 'es-hoy' => $dia->isToday()])>
                        <span>{{ $dia->isoFormat('dd') }}</span>
                        <strong>{{ $dia->format('d') }}</strong>
                    </div>
                @endforeach

                @forelse ($supervisores as $supervisora)
                    <div class="visitas-supervisora">{{ $supervisora->name }}</div>
                    @foreach ($dias as $dia)
                        @php
                            $visitas = $mapa[$supervisora->id][$dia->toDateString()] ?? collect();
                        @endphp
                        <div @class(['visitas-celda', 'es-hoy' => $dia->isToday()])>
                            @foreach ($visitas as $visita)
                                @php
                                    $local = $visita->sucursal?->nombre ?? 'Local eliminado';
                                    $punto = $visita->puntoVenta?->nombre;
                                    $detalle = $local . ($punto ? ' · ' . $punto : '') . ' · ' . $visita->fecha_hora->format('H:i');
                                @endphp
                                <span class="visita-chip" title="{{ $detalle }}">
                                    {{ $local }} · {{ $visita->fecha_hora->format('H:i') }}
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                @empty
                    <div class="visitas-vacio" style="grid-column: 1 / -1">No hay supervisoras para los filtros seleccionados.</div>
                @endforelse
            </div>
        </div>
    </x-filament::section>

    <style>
        .visitas-toolbar { display:flex; gap:1rem; justify-content:space-between; align-items:center; flex-wrap:wrap; }
        .visitas-periodo, .visitas-filtros { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
        .visitas-periodo p { margin:0; font-size:1rem; font-weight:700; text-transform:capitalize; }
        .visitas-filtros select { min-width:12rem; }
        .visitas-calendario-scroll { max-height:min(68vh, 44rem); overflow:auto; border:1px solid var(--gray-200); border-radius:.75rem; }
        .dark .visitas-calendario-scroll { border-color:var(--gray-700); }
        .visitas-calendario { display:grid; grid-template-columns:minmax(11rem, 14rem) repeat(var(--dias), minmax(5.5rem, 1fr)); min-width:max-content; }
        .dark .visitas-calendario { border-color:var(--gray-700); }
        .visitas-esquina, .visitas-dia-cabecera, .visitas-supervisora, .visitas-celda { padding:.55rem; border-right:1px solid var(--gray-200); border-bottom:1px solid var(--gray-200); }
        .dark .visitas-esquina, .dark .visitas-dia-cabecera, .dark .visitas-supervisora, .dark .visitas-celda { border-color:var(--gray-700); }
        .visitas-esquina, .visitas-dia-cabecera { background:var(--gray-50); color:var(--gray-600); font-size:.72rem; font-weight:700; }
        .dark .visitas-esquina, .dark .visitas-dia-cabecera { background:var(--gray-900); color:var(--gray-300); }
        .visitas-esquina { position:sticky; inset-block-start:0; inset-inline-start:0; z-index:30; box-shadow:1px 0 0 var(--gray-200); }
        .visitas-dia-cabecera { position:sticky; inset-block-start:0; z-index:20; box-shadow:0 1px 0 var(--gray-200); }
        .dark .visitas-esquina { box-shadow:1px 0 0 var(--gray-700); }
        .dark .visitas-dia-cabecera { box-shadow:0 1px 0 var(--gray-700); }
        .visitas-dia-cabecera { text-align:center; padding:.4rem; }
        .visitas-dia-cabecera span, .visitas-dia-cabecera strong { display:block; }
        .visitas-dia-cabecera strong { color:var(--gray-950); font-size:.9rem; }
        .dark .visitas-dia-cabecera strong { color:var(--gray-100); }
        .visitas-supervisora { position:sticky; inset-inline-start:0; z-index:10; align-content:center; background:var(--gray-50); color:var(--gray-950); font-size:.82rem; font-weight:600; box-shadow:1px 0 0 var(--gray-200); }
        .dark .visitas-supervisora { background:var(--gray-900); color:var(--gray-100); }
        .dark .visitas-supervisora { box-shadow:1px 0 0 var(--gray-700); }
        .visitas-celda { min-height:4.4rem; display:flex; flex-direction:column; gap:.25rem; background:var(--gray-0, #fff); }
        .dark .visitas-celda { background:var(--gray-950); }
        .es-hoy { background:color-mix(in srgb, var(--primary-50) 72%, transparent) !important; }
        .dark .es-hoy { background:color-mix(in srgb, var(--primary-950) 65%, transparent) !important; }
        .visita-chip { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border-radius:.3rem; background:var(--primary-100); color:var(--primary-700); font-size:.67rem; font-weight:700; line-height:1.3; padding:.22rem .3rem; }
        .dark .visita-chip { background:var(--primary-900); color:var(--primary-200); }
        .visitas-vacio { padding:1.5rem; color:var(--gray-500); text-align:center; }
        @media (max-width: 640px) { .visitas-filtros { width:100%; } .visitas-filtros select { flex:1; min-width:9rem; } }
    </style>
</x-filament-panels::page>
