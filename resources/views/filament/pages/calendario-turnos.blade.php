<x-filament-panels::page>
    <style>
        .cal-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        .cal-toolbar-nav { display: flex; align-items: center; gap: 0.5rem; }
        .cal-toolbar-mes { min-width: 11rem; text-align: center; font-weight: 600; font-size: 1rem; }
        .cal-select-wrap { width: 100%; max-width: 20rem; }
        .cal-empty { border: 1px dashed rgb(209 213 219); border-radius: 0.75rem; padding: 3rem; text-align: center; font-size: 0.9rem; color: rgb(107 114 128); }

        .cal-scroll { overflow-x: auto; border-radius: 0.75rem; border: 1px solid rgb(229 231 235); margin-top: 1.5rem; }
        .cal-grid { display: grid; grid-auto-rows: min-content; width: max-content; min-width: 100%; }

        .cal-head-turno, .cal-row-turno {
            position: sticky; left: 0; z-index: 2;
            display: flex; flex-direction: column; justify-content: center;
            white-space: nowrap;
            padding: 0.5rem 0.65rem;
            font-size: 0.8rem;
            background: #fff;
            border-right: 2px solid #e5e7eb;
            border-bottom: 1px solid #f3f4f6;
        }
        .cal-head-turno { z-index: 3; background: #f9fafb; font-weight: 600; color: #374151; }
        .cal-row-turno-nombre { font-weight: 600; color: #111827; display: flex; align-items: center; gap: 0.35rem; }
        .cal-row-turno-horario { font-size: 0.7rem; color: #9ca3af; margin-top: 0.1rem; }
        .cal-swatch { display: inline-block; width: 0.6rem; height: 0.6rem; border-radius: 999px; flex-shrink: 0; }

        .cal-day-head {
            min-width: 4rem; max-width: 4rem;
            text-align: center; padding: 0.6rem 0.1rem;
            background: #f9fafb; border-bottom: 1px solid #f3f4f6;
            border-left: 1px solid #fff;
        }
        .cal-day-dow { font-size: 0.65rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; letter-spacing: 0.02em; }
        .cal-day-num { font-size: 0.9rem; color: #4b5563; margin-top: 0.15rem; }
        .cal-day-head.cal-hoy { background: #eff6ff; }
        .cal-day-num.cal-hoy { font-weight: 700; color: #2563eb; }

        .cal-cell {
            min-width: 4rem; max-width: 4rem;
            min-height: 3.75rem;
            border-left: 1px solid #fff;
            border-bottom: 1px solid #fff;
            padding: 0.25rem;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.2rem;
        }
        .cal-cell.cal-hoy { background: #fbfdff; }
        .cal-cell-vacia {
            display: block; width: 100%; height: 100%; min-height: 3rem;
            background-color: #fafafa;
            background-image: repeating-linear-gradient(45deg, #e5e7eb 0, #e5e7eb 1px, transparent 1px, transparent 8px);
            border-radius: 0.375rem;
        }

        .cal-legend { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 1.25rem; font-size: 0.8rem; color: rgb(75 85 99); }
        .cal-legend-swatch { display: inline-block; height: 0.85rem; width: 0.85rem; border-radius: 0.25rem; margin-right: 0.4rem; vertical-align: middle; }
        .cal-actions { margin-top: 1.5rem; }
    </style>

    <x-filament::section>
        <x-slot name="heading">
            Calendario de turnos
        </x-slot>

        <div class="cal-toolbar">
            <div class="cal-toolbar-nav">
                <x-filament::icon-button icon="heroicon-o-chevron-left" label="Mes anterior" wire:click="mesAnterior" />
                <span class="cal-toolbar-mes">
                    {{ ucfirst(\Illuminate\Support\Carbon::parse($mes . '-01')->locale('es')->translatedFormat('F Y')) }}
                </span>
                <x-filament::icon-button icon="heroicon-o-chevron-right" label="Mes siguiente" wire:click="mesSiguiente" />
                <x-filament::button color="gray" size="sm" wire:click="irAHoy">Hoy</x-filament::button>
            </div>

            <div class="cal-select-wrap">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sucursalId">
                        @forelse ($this->sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @empty
                            <option value="">No hay sucursales activas</option>
                        @endforelse
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @php
            $colaboradores = $this->colaboradores;
            $turnos = $this->turnosActivos;
            $dias = $this->dias;
            $mapa = $this->mapaPorTurno;
            $hoy = now()->toDateString();
            // max-content en la primera columna: se ajusta exactamente al
            // ancho real del nombre de turno más largo + su horario, en vez
            // de un ancho fijo sobrado.
            $columnas = 'max-content repeat(' . count($dias) . ', 4rem)';
        @endphp

        @if ($colaboradores->isEmpty())
            <div class="cal-empty" style="margin-top: 1.5rem;">
                No hay colaboradores activos en esta sucursal.
            </div>
        @elseif ($turnos->isEmpty())
            <div class="cal-empty" style="margin-top: 1.5rem;">
                No hay turnos activos configurados.
            </div>
        @else
            <div class="cal-scroll">
                <div class="cal-grid" style="grid-template-columns: {{ $columnas }};">
                    <div class="cal-head-turno">Turno</div>
                    @foreach ($dias as $dia)
                        <div class="cal-day-head {{ $dia->toDateString() === $hoy ? 'cal-hoy' : '' }}">
                            <div class="cal-day-dow">{{ $dia->locale('es')->translatedFormat('D') }}</div>
                            <div class="cal-day-num {{ $dia->toDateString() === $hoy ? 'cal-hoy' : '' }}">{{ $dia->day }}</div>
                        </div>
                    @endforeach

                    @foreach ($turnos as $turno)
                        @php $color = \App\Filament\Pages\CalendarioTurnos::colorParaTurno($turno->id); @endphp
                        <div class="cal-row-turno">
                            <div class="cal-row-turno-nombre">
                                <span class="cal-swatch" style="background-color: {{ $color['bg'] }};"></span>
                                {{ $turno->nombre }}
                            </div>
                            <div class="cal-row-turno-horario">
                                {{ \Illuminate\Support\Carbon::parse($turno->hora_inicio)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($turno->hora_fin)->format('H:i') }}
                            </div>
                        </div>
                        @foreach ($dias as $dia)
                            @php
                                $lista = $mapa[$turno->id][$dia->toDateString()] ?? collect();
                            @endphp
                            <div class="cal-cell {{ $dia->toDateString() === $hoy ? 'cal-hoy' : '' }}">
                                @if ($lista->isEmpty())
                                    <span class="cal-cell-vacia" title="Nadie asignado"></span>
                                @elseif ($lista->count() <= 2)
                                    @foreach ($lista as $asignacion)
                                        @php
                                            $estado = $this->estadoAsignacion($asignacion);
                                            $icono = \App\Filament\Pages\CalendarioTurnos::iconoEstado($estado['estado']);
                                            $colorEstado = \App\Filament\Pages\CalendarioTurnos::colorEstado($estado['estado']);
                                            $tooltipTexto = $asignacion->colaborador->nombre_completo . ' — ' . $estado['label'] . ($estado['hora'] ? " ({$estado['hora']})" : '');
                                        @endphp
                                        <x-filament::badge
                                            tag="a"
                                            :href="\App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource::getUrl('edit', ['record' => $asignacion])"
                                            :color="null"
                                            :tooltip="$tooltipTexto"
                                            style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }}; cursor: pointer; position: relative; display: inline-flex; align-items: center; gap: 0.2rem;"
                                        >
                                            @if ($icono)
                                                <x-dynamic-component :component="$icono" style="width: 0.7rem; height: 0.7rem; color: {{ $colorEstado }}; flex-shrink: 0;" />
                                            @endif
                                            {{ \App\Filament\Pages\CalendarioTurnos::abreviarNombre($asignacion->colaborador->nombre_completo) }}
                                        </x-filament::badge>
                                    @endforeach
                                @else
                                    @php
                                        $peorEstado = $this->peorEstado($lista);
                                        $iconoResumen = \App\Filament\Pages\CalendarioTurnos::iconoEstado($peorEstado);
                                        $colorResumen = \App\Filament\Pages\CalendarioTurnos::colorEstado($peorEstado);
                                    @endphp
                                    <x-filament::badge
                                        :color="null"
                                        :tooltip="$this->tooltipNombres($lista)"
                                        style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }}; display: inline-flex; align-items: center; gap: 0.25rem;"
                                    >
                                        @if ($iconoResumen)
                                            <x-dynamic-component :component="$iconoResumen" style="width: 0.75rem; height: 0.75rem; color: {{ $colorResumen }}; flex-shrink: 0;" />
                                        @endif
                                        {{ $lista->count() }} colaboradores
                                    </x-filament::badge>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="cal-legend">
                @foreach ($turnos as $turno)
                    @php $color = \App\Filament\Pages\CalendarioTurnos::colorParaTurno($turno->id); @endphp
                    <span>
                        <span class="cal-legend-swatch" style="background-color: {{ $color['bg'] }};"></span>
                        {{ $turno->nombre }}
                    </span>
                @endforeach
                <span>
                    <span class="cal-legend-swatch" style="background-color: #fafafa; background-image: repeating-linear-gradient(45deg, #e5e7eb 0, #e5e7eb 1px, transparent 1px, transparent 4px);"></span>
                    Nadie asignado
                </span>
            </div>

            <div class="cal-legend" style="margin-top: 0.5rem;">
                <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                    <x-heroicon-s-check-circle style="width: 0.85rem; height: 0.85rem; color: #16a34a;" />
                    A tiempo
                </span>
                <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                    <x-heroicon-s-exclamation-triangle style="width: 0.85rem; height: 0.85rem; color: #f59e0b;" />
                    Tardanza
                </span>
                <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                    <x-heroicon-s-x-circle style="width: 0.85rem; height: 0.85rem; color: #dc2626;" />
                    Falta
                </span>
            </div>
        @endif
    </x-filament::section>

    <div class="cal-actions">
        <x-filament::button tag="a" href="{{ \App\Filament\Pages\AsignarTurnos::getUrl() }}" icon="heroicon-o-plus">
            Nueva asignación masiva
        </x-filament::button>
    </div>
</x-filament-panels::page>
