<x-filament-panels::page>
    <style>
        .jor-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; }
        .jor-nav { display:flex; align-items:center; gap:.5rem; }
        .jor-mes { min-width:11rem; text-align:center; font-weight:600; }
        .jor-select { width:100%; max-width:20rem; }
        .jor-scroll { overflow:auto; margin-top:1.5rem; border:1px solid rgb(229 231 235); border-radius:.75rem; }
        .jor-grid { display:grid; grid-auto-rows:min-content; width:max-content; min-width:100%; }
        .jor-head-persona,.jor-persona { position:sticky; left:0; z-index:2; display:flex; flex-direction:column; justify-content:center; padding:.45rem .65rem; white-space:nowrap; background:#fff; border-right:2px solid #e5e7eb; border-bottom:1px solid #f3f4f6; }
        .jor-head-persona { z-index:3; background:#f9fafb; font-size:.8rem; font-weight:600; color:#374151; }
        .jor-persona-nombre { font-size:.8rem; font-weight:600; color:#111827; }
        .jor-persona-local { margin-top:.08rem; font-size:.68rem; color:#9ca3af; }
        .jor-dia { min-width:4.7rem; max-width:4.7rem; padding:.55rem .1rem; text-align:center; background:#f9fafb; border-bottom:1px solid #f3f4f6; border-left:1px solid #fff; }
        .jor-dia-nombre { color:#9ca3af; font-size:.65rem; font-weight:600; letter-spacing:.02em; text-transform:uppercase; }
        .jor-dia-numero { margin-top:.12rem; color:#4b5563; font-size:.9rem; }
        .jor-hoy { background:#eff6ff; }
        .jor-dia-numero.jor-hoy { color:#2563eb; font-weight:700; }
        .jor-celda { min-width:4.7rem; max-width:4.7rem; min-height:3.1rem; display:flex; align-items:center; justify-content:center; padding:.35rem; border-bottom:1px solid #fff; border-left:1px solid #fff; }
        .jor-celda.jor-hoy { background:#fbfdff; }
        .jor-vacio { width:100%; height:1.75rem; border-radius:.3rem; background:repeating-linear-gradient(45deg,#e5e7eb 0,#e5e7eb 1px,transparent 1px,transparent 7px); }
        .jor-segmentos { width:100%; height:1.75rem; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); overflow:hidden; border:1px solid #d1d5db; border-radius:.35rem; background:#f3f4f6; }
        .jor-segmento { min-width:0; border-right:1px solid rgb(255 255 255 / .85); }
        .jor-segmento:last-child { border-right:0; }
        .jor-completo { background:#22c55e; }
        .jor-tardanza { background:#f59e0b; }
        .jor-faltante,.jor-inconsistente { background:#ef4444; }
        .jor-pendiente { background:#9ca3af; }
        .jor-no_iniciado,.jor-no_aplica { background:#e5e7eb; }
        .jor-leyenda { display:flex; flex-wrap:wrap; gap:1rem; margin-top:1rem; color:#4b5563; font-size:.75rem; }
        .jor-key { display:inline-flex; align-items:center; gap:.35rem; }
        .jor-key-box { width:.85rem; height:.85rem; border-radius:.2rem; }
        .dark .jor-scroll { border-color:#374151; }
        .dark .jor-head-persona,.dark .jor-persona { background:#111827; border-right-color:#374151; border-bottom-color:#1f2937; }
        .dark .jor-head-persona,.dark .jor-persona-nombre { color:#f3f4f6; }
        .dark .jor-persona-local,.dark .jor-dia-nombre { color:#9ca3af; }
        .dark .jor-dia { background:#1f2937; border-bottom-color:#374151; border-left-color:#111827; }
        .dark .jor-dia-numero { color:#d1d5db; }
        .dark .jor-hoy { background:#172554; }
        .dark .jor-dia-numero.jor-hoy { color:#93c5fd; }
        .dark .jor-celda { border-bottom-color:#1f2937; border-left-color:#1f2937; }
        .dark .jor-celda.jor-hoy { background:#111c35; }
        .dark .jor-vacio { background:repeating-linear-gradient(45deg,#374151 0,#374151 1px,transparent 1px,transparent 7px); }
        .dark .jor-segmentos { border-color:#4b5563; background:#374151; }
        .dark .jor-no_iniciado,.dark .jor-no_aplica { background:#4b5563; }
        .dark .jor-leyenda { color:#d1d5db; }
    </style>

    <x-filament::section>
        <x-slot name="heading">Control de jornadas</x-slot>

        <div class="jor-toolbar">
            <div class="jor-nav">
                <x-filament::icon-button icon="heroicon-o-chevron-left" label="Mes anterior" wire:click="mesAnterior" />
                <span class="jor-mes">{{ ucfirst(\Illuminate\Support\Carbon::parse($mes . '-01')->locale('es')->translatedFormat('F Y')) }}</span>
                <x-filament::icon-button icon="heroicon-o-chevron-right" label="Mes siguiente" wire:click="mesSiguiente" />
                <x-filament::button color="gray" size="sm" wire:click="irAHoy">Hoy</x-filament::button>
            </div>
            <div class="jor-select">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sucursalId">
                        <option value="">Todos los locales</option>
                        @foreach ($this->sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @php
            $colaboradores = $this->colaboradores;
            $dias = $this->dias;
            $asignaciones = $this->asignacionesPorColaborador;
            $segmentos = $this->segmentosPorAsignacion;
            $hoy = now()->toDateString();
            $columnas = 'max-content repeat(' . count($dias) . ', 4.7rem)';
            $etiquetas = ['entrada' => 'Entrada', 'salida_refrigerio' => 'Salida a refrigerio', 'regreso_refrigerio' => 'Retorno de refrigerio', 'salida' => 'Salida de turno'];
        @endphp

        @if ($colaboradores->isEmpty())
            <div class="jor-vacio" style="margin-top:1.5rem; height:7rem;"></div>
        @else
            <div class="jor-scroll" x-data x-init="$nextTick(() => $el.querySelector('[data-control-jornadas-hoy]')?.scrollIntoView({ block: 'nearest', inline: 'center' }))">
                <div class="jor-grid" style="grid-template-columns: {{ $columnas }};">
                    <div class="jor-head-persona">Colaborador</div>
                    @foreach ($dias as $dia)
                        <div class="jor-dia {{ $dia->toDateString() === $hoy ? 'jor-hoy' : '' }}" @if ($dia->toDateString() === $hoy) data-control-jornadas-hoy @endif>
                            <div class="jor-dia-nombre">{{ $dia->locale('es')->translatedFormat('D') }}</div>
                            <div class="jor-dia-numero {{ $dia->toDateString() === $hoy ? 'jor-hoy' : '' }}">{{ $dia->day }}</div>
                        </div>
                    @endforeach

                    @foreach ($colaboradores as $colaborador)
                        <div class="jor-persona">
                            <div class="jor-persona-nombre">{{ $colaborador->nombre_completo }}</div>
                            <div class="jor-persona-local">{{ $colaborador->sucursal->nombre }}</div>
                        </div>
                        @foreach ($dias as $dia)
                            @php
                                $asignacion = $asignaciones[$colaborador->id][$dia->toDateString()] ?? null;
                                $segmentosDia = $asignacion ? ($segmentos[$asignacion->id] ?? []) : [];
                            @endphp
                            <div class="jor-celda {{ $dia->toDateString() === $hoy ? 'jor-hoy' : '' }}">
                                @if (! $asignacion)
                                    <span class="jor-vacio" title="Sin turno asignado"></span>
                                @else
                                    <div class="jor-segmentos" title="{{ $asignacion->turno->nombre }} · {{ $asignacion->turno->rangoHorario() }}">
                                        @foreach ($etiquetas as $clave => $etiqueta)
                                            @php $segmento = $segmentosDia[$clave]; @endphp
                                            <span
                                                class="jor-segmento jor-{{ $segmento['estado'] }}"
                                                title="{{ $etiqueta }}: {{ $segmento['etiqueta'] }}{{ $segmento['hora'] ? ' · ' . $segmento['hora'] : '' }}"
                                                aria-label="{{ $etiqueta }}: {{ $segmento['etiqueta'] }}{{ $segmento['hora'] ? ' · ' . $segmento['hora'] : '' }}"
                                            ></span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="jor-leyenda">
                <span>Entrada · Salida a refrigerio · Retorno · Salida</span>
                <span class="jor-key"><i class="jor-key-box jor-completo"></i>Registrado</span>
                <span class="jor-key"><i class="jor-key-box jor-tardanza"></i>Fuera de hora</span>
                <span class="jor-key"><i class="jor-key-box jor-faltante"></i>Faltante</span>
                <span class="jor-key"><i class="jor-key-box jor-pendiente"></i>Pendiente</span>
            </div>
        @endif
    </x-filament::section>

    @script
        $wire.on('control-jornadas-ir-a-hoy', () => {
            document.querySelector('[data-control-jornadas-hoy]')?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' })
        })
    @endscript
</x-filament-panels::page>
