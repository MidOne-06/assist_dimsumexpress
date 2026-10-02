<x-filament-panels::page>
    <style>
        .jornada-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; }
        .jornada-toolbar__navigation { display:flex; align-items:center; gap:.5rem; }
        .jornada-toolbar__month { min-width:10rem; text-align:center; font-weight:600; }
        .jornada-toolbar__select { width:min(100%, 19rem); }

        .jornada-empty { border:1px dashed var(--gray-300, #d1d5db); border-radius:.75rem; padding:2.5rem 1rem; color:var(--gray-500, #6b7280); text-align:center; }
        .jornada-scroll { overflow-x:auto; border:1px solid var(--gray-200, #e5e7eb); border-radius:.75rem; background:var(--gray-50, #f9fafb); }
        .jornada-grid { display:grid; grid-template-columns:15.25rem repeat(var(--jornada-dias), 6.25rem); width:max-content; min-width:100%; }
        .jornada-person-head, .jornada-day-head { min-height:5.25rem; border-bottom:1px solid var(--gray-200, #e5e7eb); background:var(--gray-50, #f9fafb); }
        .jornada-person-head { position:sticky; left:0; z-index:5; padding:.75rem; border-right:1px solid var(--gray-200, #e5e7eb); }
        .jornada-day-head { position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; justify-content:center; min-width:6.25rem; border-left:1px solid var(--gray-200, #e5e7eb); color:var(--gray-600, #4b5563); }
        .jornada-day-head--today { background:color-mix(in srgb, var(--primary-500, #f59e0b) 9%, var(--gray-50, #f9fafb)); color:var(--primary-700, #a16207); }
        .jornada-day-head__dow { font-size:.7rem; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
        .jornada-day-head__number { font-size:1rem; font-weight:700; }

        .jornada-person { position:sticky; left:0; z-index:4; min-height:28rem; padding:.75rem; border-right:1px solid var(--gray-200, #e5e7eb); background:var(--gray-50, #f9fafb); }
        .jornada-person__select { margin-bottom:.75rem; }
        .jornada-person__profile { display:flex; gap:.625rem; align-items:center; min-height:3.5rem; }
        .jornada-avatar { display:grid; place-items:center; width:2.5rem; height:2.5rem; flex:none; border-radius:9999px; background:var(--primary-100, #fef3c7); color:var(--primary-700, #a16207); font-size:.75rem; font-weight:700; }
        .jornada-person__name { color:var(--gray-950, #030712); font-size:.875rem; font-weight:700; line-height:1.2; }
        .jornada-person__role { margin-top:.15rem; color:var(--gray-500, #6b7280); font-size:.75rem; line-height:1.25; }
        .jornada-scale { position:relative; height:28rem; margin-top:1rem; border-top:1px solid var(--gray-200, #e5e7eb); }
        .jornada-scale__label { position:absolute; right:.65rem; transform:translateY(-50%); color:var(--gray-400, #9ca3af); font-size:.6875rem; font-variant-numeric:tabular-nums; }
        .jornada-scale__label:first-child { transform:translateY(0); }
        .jornada-scale__label:last-child { transform:translateY(-100%); }

        .jornada-day { position:relative; min-width:6.25rem; min-height:28rem; border-left:1px solid var(--gray-200, #e5e7eb); background-color:var(--gray-50, #f9fafb); background-image:linear-gradient(to bottom, transparent calc(12.5% - 1px), var(--gray-200, #e5e7eb) calc(12.5% - 1px), var(--gray-200, #e5e7eb) 12.5%, transparent 12.5%); background-size:100% 12.5%; }
        .jornada-day--today { background-color:color-mix(in srgb, var(--primary-500, #f59e0b) 4%, var(--gray-50, #f9fafb)); }
        .jornada-day--empty { display:grid; place-items:center; background-color:var(--gray-100, #f3f4f6); background-image:repeating-linear-gradient(45deg, color-mix(in srgb, var(--gray-300, #d1d5db) 52%, transparent) 0 1px, transparent 1px 7px); }
        .jornada-day--empty span { border:1px solid var(--gray-200, #e5e7eb); border-radius:.375rem; padding:.1875rem .375rem; background:color-mix(in srgb, var(--gray-50, #f9fafb) 88%, transparent); color:var(--gray-500, #6b7280); font-size:.6875rem; }
        .jornada-day__unmarked { position:absolute; top:.625rem; right:.375rem; left:.375rem; text-align:center; color:var(--gray-500, #6b7280); font-size:.6875rem; line-height:1.2; }
        .jornada-span { position:absolute; right:.9rem; left:.9rem; min-height:.25rem; border:1px solid color-mix(in srgb, var(--success-500, #22c55e) 45%, transparent); border-radius:.375rem; background:color-mix(in srgb, var(--success-500, #22c55e) 18%, transparent); }
        .jornada-span--open { border-style:dashed; }
        .jornada-break { position:absolute; right:calc(.9rem + 1px); left:calc(.9rem + 1px); min-height:.25rem; border-radius:.25rem; background:color-mix(in srgb, var(--warning-500, #f59e0b) 32%, transparent); }
        .jornada-break--incident { background:color-mix(in srgb, var(--danger-500, #ef4444) 24%, transparent); }
        .jornada-marker { position:absolute; z-index:2; left:50%; transform:translate(-50%, -50%); white-space:nowrap; font-size:.625rem; font-variant-numeric:tabular-nums; line-height:1; }
        .jornada-marker .fi-badge { min-height:1.25rem; padding-inline:.3rem; }
        .jornada-marker--entrada { color:var(--success-700, #15803d); }
        .jornada-marker--salida-refrigerio { color:var(--warning-700, #a16207); }
        .jornada-marker--regreso-refrigerio { color:var(--info-700, #0369a1); }
        .jornada-marker--salida { color:var(--danger-700, #b91c1c); }

        .dark .jornada-empty, .dark .jornada-scroll { border-color:var(--gray-700, #374151); }
        .dark .jornada-scroll, .dark .jornada-person-head, .dark .jornada-day-head, .dark .jornada-person, .dark .jornada-day { background-color:var(--gray-900, #111827); }
        .dark .jornada-person-head, .dark .jornada-person, .dark .jornada-day-head, .dark .jornada-day { border-color:var(--gray-800, #1f2937); }
        .dark .jornada-day { background-image:linear-gradient(to bottom, transparent calc(12.5% - 1px), var(--gray-800, #1f2937) calc(12.5% - 1px), var(--gray-800, #1f2937) 12.5%, transparent 12.5%); }
        .dark .jornada-day--empty { background-color:var(--gray-950, #030712); background-image:repeating-linear-gradient(45deg, color-mix(in srgb, var(--gray-700, #374151) 62%, transparent) 0 1px, transparent 1px 7px); }
        .dark .jornada-person__name { color:var(--gray-50, #f9fafb); }
        .dark .jornada-day-head--today, .dark .jornada-day--today { background-color:color-mix(in srgb, var(--primary-500, #f59e0b) 13%, var(--gray-900, #111827)); }
        @media (max-width: 640px) { .jornada-grid { grid-template-columns:13rem repeat(var(--jornada-dias), 5.5rem); } .jornada-person-head, .jornada-person { width:13rem; } .jornada-day { min-width:5.5rem; } .jornada-toolbar__select { width:100%; } }
    </style>

    <x-filament::section compact>
        <div class="jornada-toolbar">
            <div class="jornada-toolbar__navigation">
                <x-filament::icon-button icon="heroicon-o-chevron-left" label="Mes anterior" wire:click="mesAnterior" />
                <span class="jornada-toolbar__month">{{ ucfirst(\Carbon\Carbon::parse($mes . '-01')->locale('es')->translatedFormat('F Y')) }}</span>
                <x-filament::icon-button icon="heroicon-o-chevron-right" label="Mes siguiente" wire:click="mesSiguiente" />
                <x-filament::button color="gray" size="sm" wire:click="irAHoy">Hoy</x-filament::button>
            </div>

            <div class="jornada-toolbar__select">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sucursalId" aria-label="Local">
                        <option value="">Todos los locales</option>
                        @foreach ($this->sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    @php
        $colaborador = $this->colaborador;
        $jornadas = $this->jornadas;
        $hoy = now()->toDateString();
    @endphp

    @if (! $colaborador)
        <div class="jornada-empty">No hay colaboradores activos para el local seleccionado.</div>
    @else
        <div class="jornada-scroll" x-data x-init="$nextTick(() => $el.querySelector('[data-control-jornadas-hoy]')?.scrollIntoView({ block: 'nearest', inline: 'center' }))">
            <div class="jornada-grid" style="--jornada-dias: {{ $jornadas->count() }};">
                <div class="jornada-person-head">
                    <div class="jornada-person__select">
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="colaboradorId" aria-label="Colaborador">
                                @foreach ($this->colaboradores as $opcion)
                                    <option value="{{ $opcion->id }}">{{ $opcion->nombre_completo }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <div class="jornada-person__profile">
                        <span class="jornada-avatar">{{ \App\Filament\Pages\ControlJornadas::iniciales($colaborador->nombre_completo) }}</span>
                        <div>
                            <div class="jornada-person__name">{{ $colaborador->nombre_completo }}</div>
                            <div class="jornada-person__role">{{ $colaborador->cargo ?: 'Colaborador' }}{{ $colaborador->area ? ' · ' . $colaborador->area->nombre : '' }}</div>
                        </div>
                    </div>
                </div>

                @foreach ($jornadas as $jornada)
                    @php $fecha = $jornada['fecha']; $esHoy = $fecha->toDateString() === $hoy; @endphp
                    <div class="jornada-day-head {{ $esHoy ? 'jornada-day-head--today' : '' }}" @if ($esHoy) data-control-jornadas-hoy @endif>
                        <span class="jornada-day-head__dow">{{ $fecha->locale('es')->translatedFormat('D') }}</span>
                        <span class="jornada-day-head__number">{{ $fecha->day }}</span>
                    </div>
                @endforeach

                <aside class="jornada-person" aria-label="Colaborador seleccionado">
                    <div class="jornada-scale" aria-label="Escala horaria">
                        @foreach (\App\Filament\Pages\ControlJornadas::horasEscala() as $hora)
                            <span class="jornada-scale__label" style="top: {{ (($hora - 6) / 16) * 100 }}%">{{ str_pad((string) $hora, 2, '0', STR_PAD_LEFT) }}:00</span>
                        @endforeach
                    </div>
                </aside>

                @foreach ($jornadas as $jornada)
                    @php
                        $asignacion = $jornada['asignacion'];
                        $eventos = $jornada['marcaciones'];
                        $rango = $jornada['jornada'];
                        $refrigerio = $jornada['refrigerio'];
                        $esHoy = $jornada['fecha']->toDateString() === $hoy;
                    @endphp
                    @if (! $asignacion)
                        <div class="jornada-day jornada-day--empty {{ $esHoy ? 'jornada-day--today' : '' }}"><span>Sin turno</span></div>
                        @continue
                    @endif

                    <div class="jornada-day {{ $esHoy ? 'jornada-day--today' : '' }}" title="{{ $asignacion->turno->nombre }} · {{ $asignacion->turno->rangoHorario() }}">
                        @if (! $rango)
                            <span class="jornada-day__unmarked">Sin marcaciones</span>
                        @else
                            <div class="jornada-span {{ ! $rango['cerrada'] ? 'jornada-span--open' : '' }}" style="top: {{ $rango['inicio'] }}%; height: {{ max(.75, $rango['fin'] - $rango['inicio']) }}%"></div>
                        @endif

                        @if ($refrigerio)
                            <div class="jornada-break {{ $refrigerio['incidencia'] ? 'jornada-break--incident' : '' }}" style="top: {{ $refrigerio['inicio'] }}%; height: {{ max(.75, $refrigerio['fin'] - $refrigerio['inicio']) }}%"></div>
                        @endif

                        @foreach ($eventos as $evento)
                            @php
                                $tipo = $evento->tipo;
                                $color = match ($tipo) {
                                    \App\Models\Marcacion::TIPO_ENTRADA => 'success',
                                    \App\Models\Marcacion::TIPO_SALIDA_REFRIGERIO => 'warning',
                                    \App\Models\Marcacion::TIPO_REGRESO_REFRIGERIO => 'info',
                                    default => 'danger',
                                };
                            @endphp
                            <span class="jornada-marker jornada-marker--{{ str_replace('_', '-', $tipo) }}" style="top: {{ \App\Filament\Pages\ControlJornadas::porcentajeHora($evento->fecha_hora) }}%" title="{{ str_replace('_', ' ', ucfirst($tipo)) }}">
                                <x-filament::badge :color="$color">● {{ $evento->fecha_hora->format('H:i') }}</x-filament::badge>
                            </span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>

@script
<script>
    $wire.on('control-jornadas-ir-a-hoy', () => {
        document.querySelector('[data-control-jornadas-hoy]')?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' })
    })
</script>
@endscript
