<x-filament-panels::page>
    <style>
        .attendance-matrix-filters { display:grid; gap:.75rem; grid-template-columns:repeat(1, minmax(0, 1fr)); }
        .attendance-matrix-filter { min-width:0; }
        .attendance-matrix-filter label { display:block; margin-bottom:.35rem; color:var(--gray-700, #374151); font-size:.75rem; font-weight:600; }
        .attendance-matrix-actions { display:flex; align-items:end; }
        .attendance-matrix-actions .fi-btn { width:100%; }
        .attendance-matrix-scroll { margin-top:1.25rem; overflow-x:auto; border:1px solid var(--gray-200, #e5e7eb); border-radius:.875rem; background:var(--gray-50, #f9fafb); }
        .attendance-matrix-grid { display:grid; width:max-content; min-width:100%; }
        .attendance-matrix-head, .attendance-matrix-person, .attendance-matrix-day { border-bottom:1px solid var(--gray-200, #e5e7eb); }
        .attendance-matrix-head { min-height:4.75rem; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:.5rem; background:var(--gray-100, #f3f4f6); color:var(--gray-700, #374151); }
        .attendance-matrix-head--person { position:sticky; left:0; z-index:4; align-items:flex-start; border-right:2px solid var(--gray-200, #e5e7eb); }
        .attendance-matrix-head--today { background:color-mix(in srgb, var(--primary-500, #0ea5e9) 10%, var(--gray-100, #f3f4f6)); color:var(--primary-700, #0369a1); }
        .attendance-matrix-day-name { font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .attendance-matrix-day-number { margin-top:.15rem; font-size:1rem; font-weight:700; }
        .attendance-matrix-person { position:sticky; left:0; z-index:3; min-height:6rem; display:flex; align-items:center; gap:.65rem; padding:.75rem; background:var(--gray-50, #f9fafb); border-right:2px solid var(--gray-200, #e5e7eb); }
        .attendance-matrix-avatar { display:grid; width:2.25rem; height:2.25rem; flex:none; place-items:center; border-radius:999px; background:var(--primary-100, #e0f2fe); color:var(--primary-700, #0369a1); font-size:.72rem; font-weight:700; }
        .attendance-matrix-person-name { color:var(--gray-950, #030712); font-size:.85rem; font-weight:700; line-height:1.25; }
        .attendance-matrix-person-meta { margin-top:.18rem; color:var(--gray-500, #6b7280); font-size:.72rem; line-height:1.3; }
        .attendance-matrix-day { min-width:8.25rem; min-height:6rem; display:flex; flex-direction:column; gap:.22rem; justify-content:center; padding:.45rem; border-left:1px solid var(--gray-200, #e5e7eb); background:var(--gray-50, #f9fafb); }
        .attendance-matrix-day--today { background:color-mix(in srgb, var(--primary-500, #0ea5e9) 4%, var(--gray-50, #f9fafb)); }
        .attendance-matrix-empty { color:var(--gray-400, #9ca3af); font-size:.74rem; text-align:center; }
        .attendance-matrix-mark { display:flex; align-items:center; justify-content:space-between; gap:.4rem; font-size:.7rem; line-height:1.1; }
        .attendance-matrix-mark time { color:var(--gray-700, #374151); font-variant-numeric:tabular-nums; font-weight:700; }
        .attendance-matrix-pager { margin-top:1rem; }
        .attendance-matrix-summary { margin-top:1rem; color:var(--gray-600, #4b5563); font-size:.8rem; }

        @media (min-width: 640px) { .attendance-matrix-filters { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .attendance-matrix-filters { grid-template-columns:repeat(4, minmax(0, 1fr)); } }

        .dark .attendance-matrix-filter label { color:var(--gray-300, #d1d5db); }
        .dark .attendance-matrix-scroll { border-color:var(--gray-700, #374151); background:var(--gray-900, #111827); }
        .dark .attendance-matrix-head { background:var(--gray-800, #1f2937); color:var(--gray-200, #e5e7eb); border-bottom-color:var(--gray-700, #374151); }
        .dark .attendance-matrix-head--person, .dark .attendance-matrix-person { border-right-color:var(--gray-700, #374151); }
        .dark .attendance-matrix-head--today { background:color-mix(in srgb, var(--primary-500, #0ea5e9) 22%, var(--gray-800, #1f2937)); color:var(--primary-300, #7dd3fc); }
        .dark .attendance-matrix-person, .dark .attendance-matrix-day { background:var(--gray-900, #111827); border-bottom-color:var(--gray-800, #1f2937); }
        .dark .attendance-matrix-day { border-left-color:var(--gray-800, #1f2937); }
        .dark .attendance-matrix-day--today { background:color-mix(in srgb, var(--primary-500, #0ea5e9) 10%, var(--gray-900, #111827)); }
        .dark .attendance-matrix-person-name { color:var(--gray-100, #f3f4f6); }
        .dark .attendance-matrix-person-meta, .dark .attendance-matrix-mark time, .dark .attendance-matrix-summary { color:var(--gray-400, #9ca3af); }
    </style>

    @php
        $dias = $this->dias;
        $filas = $this->filasPaginadas;
        $marcaciones = $this->marcacionesPorColaboradorYDia;
        $hoy = now()->toDateString();
        $columnas = '18rem repeat(' . $dias->count() . ', 8.25rem)';
    @endphp

    <x-filament::section>
        <div class="attendance-matrix-filters">
            <div class="attendance-matrix-filter">
                <label for="matriz-mes">Mes</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="matriz-mes" type="month" wire:model.live="mes" />
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-empresa">Empresa</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-empresa" wire:model.live="empresaId">
                        <option value="">Todas</option>
                        @foreach ($this->empresas as $empresa)
                            <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-area">Área</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-area" wire:model.live="areaId">
                        <option value="">Todas</option>
                        @foreach ($this->areas as $area)
                            <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-sucursal">Local base</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-sucursal" wire:model.live="sucursalId">
                        <option value="">Todos</option>
                        @foreach ($this->sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->activo ? '' : ' · Histórico' }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-punto-venta">Punto de venta base</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-punto-venta" wire:model.live="puntoVentaId">
                        <option value="">Todos</option>
                        @foreach ($this->puntosVenta as $puntoVenta)
                            <option value="{{ $puntoVenta->id }}">{{ $puntoVenta->sucursal?->nombre }} · {{ $puntoVenta->nombre }}{{ $puntoVenta->activo ? '' : ' · Histórico' }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-cargo">Cargo</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-cargo" wire:model.live="cargo">
                        <option value="">Todos</option>
                        @foreach ($this->cargos as $opcionCargo)
                            <option value="{{ $opcionCargo }}">{{ $opcionCargo }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-colaborador">Colaborador</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-colaborador" wire:model.live="colaboradorId">
                        <option value="">Todos</option>
                        @foreach ($this->opcionesColaboradores as $colaborador)
                            <option value="{{ $colaborador->id }}">{{ $colaborador->nombre_completo }}{{ $colaborador->activo ? '' : ' · Histórico' }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-estado-colaborador">Estado del colaborador</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-estado-colaborador" wire:model.live="estadoColaborador">
                        <option value="todos">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Históricos</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-estado-marcacion">Marcación del mes</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="matriz-estado-marcacion" wire:model.live="estadoMarcacion">
                        @foreach ($this->estadosMarcacion() as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-filter">
                <label for="matriz-busqueda">Buscar</label>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input id="matriz-busqueda" type="search" wire:model.live.debounce.400ms="busqueda" placeholder="Nombre, documento o código" />
                </x-filament::input.wrapper>
            </div>

            <div class="attendance-matrix-actions">
                <x-filament::button color="gray" wire:click="limpiarFiltros" icon="heroicon-o-x-mark">
                    Limpiar filtros
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    @if ($filas->isEmpty())
        <x-filament::section class="mt-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay colaboradores para los filtros seleccionados.</p>
        </x-filament::section>
    @else
        <p class="attendance-matrix-summary">
            Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} colaboradores.
        </p>

        <div
            class="attendance-matrix-scroll"
            x-data
            x-init="$nextTick(() => $el.querySelector('[data-matriz-hoy]')?.scrollIntoView({ block: 'nearest', inline: 'center' }))"
        >
            <div class="attendance-matrix-grid" style="grid-template-columns: {{ $columnas }};">
                <div class="attendance-matrix-head attendance-matrix-head--person">Colaborador</div>
                @foreach ($dias as $dia)
                    <div
                        class="attendance-matrix-head {{ $dia->toDateString() === $hoy ? 'attendance-matrix-head--today' : '' }}"
                        @if ($dia->toDateString() === $hoy) data-matriz-hoy @endif
                    >
                        <span class="attendance-matrix-day-name">{{ $dia->locale('es')->translatedFormat('D') }}</span>
                        <span class="attendance-matrix-day-number">{{ $dia->day }}</span>
                    </div>
                @endforeach

                @foreach ($filas as $colaborador)
                    <div class="attendance-matrix-person">
                        <span class="attendance-matrix-avatar">{{ $colaborador->nombre_completo ? collect(explode(' ', $colaborador->nombre_completo))->filter()->take(2)->map(fn (string $nombre): string => mb_strtoupper(mb_substr($nombre, 0, 1)))->implode('') : '—' }}</span>
                        <div>
                            <div class="attendance-matrix-person-name">{{ $colaborador->nombre_completo }}</div>
                            <div class="attendance-matrix-person-meta">
                                {{ collect([$colaborador->cargo, $colaborador->sucursal?->nombre, $colaborador->puntoVenta?->nombre])->filter()->implode(' · ') ?: 'Sin detalle organizacional' }}
                            </div>
                        </div>
                    </div>

                    @foreach ($dias as $dia)
                        @php $eventos = $marcaciones[$colaborador->id][$dia->toDateString()] ?? collect(); @endphp
                        <div class="attendance-matrix-day {{ $dia->toDateString() === $hoy ? 'attendance-matrix-day--today' : '' }}">
                            @forelse ($eventos as $evento)
                                <div class="attendance-matrix-mark" title="{{ \App\Services\MatrizMarcacionesService::etiquetaTipo($evento->tipo) }}">
                                    <x-filament::badge :color="\App\Services\MatrizMarcacionesService::colorTipo($evento->tipo)" size="sm">
                                        {{ \App\Services\MatrizMarcacionesService::etiquetaTipo($evento->tipo) }}
                                    </x-filament::badge>
                                    <time datetime="{{ $evento->fecha_hora->toIso8601String() }}">{{ $evento->fecha_hora->format('H:i') }}</time>
                                </div>
                            @empty
                                <span class="attendance-matrix-empty">Sin marcaciones</span>
                            @endforelse
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="attendance-matrix-pager">
            {{ $filas->onEachSide(1)->links() }}
        </div>
    @endif
</x-filament-panels::page>

@script
<script>
    $wire.on('matriz-marcaciones-ir-a-hoy', () => {
        document.querySelector('[data-matriz-hoy]')?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' })
    })
</script>
@endscript
