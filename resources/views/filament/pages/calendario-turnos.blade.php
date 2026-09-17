<x-filament-panels::page>
    <style>
        .cal-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
        .cal-toolbar-nav { display: flex; align-items: center; gap: 0.5rem; }
        .cal-toolbar-mes { min-width: 10rem; text-align: center; font-weight: 600; font-size: 0.95rem; }
        .cal-select { width: 100%; max-width: 18rem; border-radius: 0.5rem; border: 1px solid rgb(209 213 219); padding: 0.4rem 0.6rem; font-size: 0.875rem; }
        .cal-empty { border: 1px dashed rgb(209 213 219); border-radius: 0.75rem; padding: 2.5rem; text-align: center; font-size: 0.875rem; color: rgb(107 114 128); }

        .cal-scroll { overflow-x: auto; border-radius: 0.75rem; }
        .cal-grid { display: grid; grid-auto-rows: min-content; width: max-content; min-width: 100%; }

        .cal-head-nombre, .cal-col-nombre {
            position: sticky; left: 0; z-index: 2;
            display: flex; align-items: center;
            min-width: 11rem; max-width: 11rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            background: #fff;
            border-right: 2px solid #e5e7eb;
            border-bottom: 1px solid #f3f4f6;
        }
        .cal-head-nombre { z-index: 3; background: #f9fafb; font-weight: 600; color: #374151; }
        .cal-col-nombre { font-weight: 500; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .cal-day-head {
            min-width: 2.35rem; max-width: 2.35rem;
            text-align: center; padding: 0.4rem 0.1rem;
            background: #f9fafb; border-bottom: 1px solid #f3f4f6;
            border-left: 1px solid #fff;
        }
        .cal-day-dow { font-size: 0.55rem; text-transform: uppercase; color: #9ca3af; }
        .cal-day-num { font-size: 0.75rem; color: #4b5563; }
        .cal-day-head.cal-hoy { background: #eff6ff; }
        .cal-day-num.cal-hoy { font-weight: 700; color: #2563eb; }

        .cal-cell {
            min-width: 2.35rem; max-width: 2.35rem;
            min-height: 2.6rem;
            border-left: 1px solid #fff;
            border-bottom: 1px solid #fff;
            position: relative;
        }
        .cal-cell-fill {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            width: 100%; height: 2.6rem;
            font-size: 0.6rem; font-weight: 700;
            text-decoration: none;
        }
        .cal-cell-fill .icono { opacity: 0.85; margin-top: 0.15rem; font-size: 0.7rem; }
        .cal-cell-vacia {
            display: block; width: 100%; height: 2.6rem;
            background-color: #f3f4f6;
            background-image: repeating-linear-gradient(45deg, #e5e7eb 0, #e5e7eb 1px, transparent 1px, transparent 7px);
        }

        .cal-legend { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.75rem; color: rgb(75 85 99); }
        .cal-legend-swatch { display: inline-block; height: 0.75rem; width: 0.75rem; border-radius: 0.25rem; margin-right: 0.375rem; vertical-align: middle; }
        .cal-actions { margin-top: 1.5rem; }
    </style>

    <div class="cal-toolbar">
        <div class="cal-toolbar-nav">
            <x-filament::icon-button icon="heroicon-o-chevron-left" label="Mes anterior" wire:click="mesAnterior" />
            <span class="cal-toolbar-mes">
                {{ ucfirst(\Illuminate\Support\Carbon::parse($mes . '-01')->locale('es')->translatedFormat('F Y')) }}
            </span>
            <x-filament::icon-button icon="heroicon-o-chevron-right" label="Mes siguiente" wire:click="mesSiguiente" />
            <x-filament::button color="gray" size="sm" wire:click="irAHoy">Hoy</x-filament::button>
        </div>

        <select wire:model.live="sucursalId" class="cal-select">
            @forelse ($this->sucursales as $sucursal)
                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
            @empty
                <option value="">No hay sucursales activas</option>
            @endforelse
        </select>
    </div>

    @php
        $colaboradores = $this->colaboradores;
        $dias = $this->dias;
        $mapa = $this->mapaAsignaciones;
        $hoy = now()->toDateString();
        $columnas = '11rem repeat(' . count($dias) . ', 2.35rem)';
    @endphp

    @if ($colaboradores->isEmpty())
        <div class="cal-empty">
            No hay colaboradores activos en esta sucursal. Registra colaboradores o cambia el filtro.
        </div>
    @else
        <div class="cal-scroll">
            <div class="cal-grid" style="grid-template-columns: {{ $columnas }};">
                <div class="cal-head-nombre">Colaborador</div>
                @foreach ($dias as $dia)
                    <div class="cal-day-head {{ $dia->toDateString() === $hoy ? 'cal-hoy' : '' }}">
                        <div class="cal-day-dow">{{ $dia->locale('es')->translatedFormat('D') }}</div>
                        <div class="cal-day-num {{ $dia->toDateString() === $hoy ? 'cal-hoy' : '' }}">{{ $dia->day }}</div>
                    </div>
                @endforeach

                @foreach ($colaboradores as $colaborador)
                    <div class="cal-col-nombre" title="{{ $colaborador->nombre_completo }}">{{ $colaborador->nombre_completo }}</div>
                    @foreach ($dias as $dia)
                        @php
                            $asignacion = $mapa[$colaborador->id][$dia->toDateString()] ?? null;
                            $turno = $asignacion?->turno;
                            $color = $turno ? \App\Filament\Pages\CalendarioTurnos::colorParaTurno($turno->id) : null;
                        @endphp
                        <div class="cal-cell">
                            @if ($turno)
                                <a
                                    href="{{ \App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource::getUrl('edit', ['record' => $asignacion]) }}"
                                    title="{{ $turno->nombre }} ({{ \Illuminate\Support\Carbon::parse($turno->hora_inicio)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($turno->hora_fin)->format('H:i') }}) — clic para editar"
                                    class="cal-cell-fill"
                                    style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }};"
                                >
                                    <span>{{ \Illuminate\Support\Str::of($turno->nombre)->substr(0, 3) }}</span>
                                    <span class="icono">↻</span>
                                </a>
                            @else
                                <span class="cal-cell-vacia" title="Sin turno asignado"></span>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        @if ($this->turnosActivos->isNotEmpty())
            <div class="cal-legend">
                @foreach ($this->turnosActivos as $turno)
                    @php $color = \App\Filament\Pages\CalendarioTurnos::colorParaTurno($turno->id); @endphp
                    <span>
                        <span class="cal-legend-swatch" style="background-color: {{ $color['bg'] }};"></span>
                        {{ $turno->nombre }}
                        ({{ \Illuminate\Support\Carbon::parse($turno->hora_inicio)->format('H:i') }}-{{ \Illuminate\Support\Carbon::parse($turno->hora_fin)->format('H:i') }})
                    </span>
                @endforeach
                <span>
                    <span class="cal-legend-swatch" style="background-color: #f3f4f6; background-image: repeating-linear-gradient(45deg, #e5e7eb 0, #e5e7eb 1px, transparent 1px, transparent 4px);"></span>
                    Sin turno asignado
                </span>
            </div>
        @endif
    @endif

    <div class="cal-actions">
        <x-filament::button tag="a" href="{{ \App\Filament\Pages\AsignarTurnos::getUrl() }}" icon="heroicon-o-plus">
            Nueva asignación masiva
        </x-filament::button>
    </div>
</x-filament-panels::page>
