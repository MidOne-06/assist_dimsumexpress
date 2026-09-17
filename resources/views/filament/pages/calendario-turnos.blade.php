<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <x-filament::icon-button
                icon="heroicon-o-chevron-left"
                label="Mes anterior"
                wire:click="mesAnterior"
            />
            <span class="min-w-[10rem] text-center text-base font-semibold text-gray-950 dark:text-white">
                {{ ucfirst(\Illuminate\Support\Carbon::parse($mes . '-01')->locale('es')->translatedFormat('F Y')) }}
            </span>
            <x-filament::icon-button
                icon="heroicon-o-chevron-right"
                label="Mes siguiente"
                wire:click="mesSiguiente"
            />
            <x-filament::button color="gray" size="sm" wire:click="irAHoy">
                Hoy
            </x-filament::button>
        </div>

        <div class="w-full sm:w-72">
            <select
                wire:model.live="sucursalId"
                class="fi-select-input block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
            >
                @forelse ($this->sucursales as $sucursal)
                    <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                @empty
                    <option value="">No hay sucursales activas</option>
                @endforelse
            </select>
        </div>
    </div>

    @php
        $colaboradores = $this->colaboradores;
        $dias = $this->dias;
        $mapa = $this->mapaAsignaciones;
        $hoy = now()->toDateString();
    @endphp

    @if ($colaboradores->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            No hay colaboradores activos en esta sucursal. Registra colaboradores o cambia el filtro.
        </div>
    @else
        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="sticky left-0 z-10 min-w-[12rem] border-b border-gray-200 bg-gray-50 px-3 py-2 text-left font-medium text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200">
                            Colaborador
                        </th>
                        @foreach ($dias as $dia)
                            <th class="min-w-[2.5rem] border-b border-gray-200 px-1 py-2 text-center font-medium dark:border-white/10 {{ $dia->toDateString() === $hoy ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                                <div class="text-[10px] uppercase text-gray-400">{{ $dia->locale('es')->translatedFormat('D') }}</div>
                                <div class="{{ $dia->toDateString() === $hoy ? 'font-bold text-primary-600 dark:text-primary-400' : 'text-gray-600 dark:text-gray-300' }}">
                                    {{ $dia->day }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($colaboradores as $colaborador)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                            <td class="sticky left-0 z-10 whitespace-nowrap border-r border-gray-100 bg-white px-3 py-1.5 font-medium text-gray-900 dark:border-white/5 dark:bg-gray-900 dark:text-gray-100">
                                {{ $colaborador->nombre_completo }}
                            </td>
                            @foreach ($dias as $dia)
                                @php
                                    $turno = $mapa[$colaborador->id][$dia->toDateString()] ?? null;
                                    $color = $turno ? \App\Filament\Pages\CalendarioTurnos::colorParaTurno($turno->id) : null;
                                @endphp
                                <td class="p-1 text-center {{ $dia->toDateString() === $hoy ? 'bg-primary-50/50 dark:bg-primary-500/5' : '' }}">
                                    @if ($turno)
                                        <span
                                            title="{{ $turno->nombre }} ({{ \Illuminate\Support\Carbon::parse($turno->hora_inicio)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($turno->hora_fin)->format('H:i') }})"
                                            class="{{ $color['bg'] }} {{ $color['text'] }} flex h-6 w-full items-center justify-center rounded text-[10px] font-semibold"
                                        >
                                            {{ \Illuminate\Support\Str::of($turno->nombre)->substr(0, 3) }}
                                        </span>
                                    @else
                                        <span class="block h-6 w-full rounded border border-dashed border-gray-200 dark:border-white/10"></span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->turnosActivos->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-300">
                @foreach ($this->turnosActivos as $turno)
                    @php $color = \App\Filament\Pages\CalendarioTurnos::colorParaTurno($turno->id); @endphp
                    <span class="inline-flex items-center gap-1.5">
                        <span class="{{ $color['bg'] }} inline-block h-3 w-3 rounded"></span>
                        {{ $turno->nombre }}
                        ({{ \Illuminate\Support\Carbon::parse($turno->hora_inicio)->format('H:i') }}-{{ \Illuminate\Support\Carbon::parse($turno->hora_fin)->format('H:i') }})
                    </span>
                @endforeach
            </div>
        @endif
    @endif

    <div class="mt-6">
        <x-filament::button tag="a" href="{{ \App\Filament\Pages\AsignarTurnos::getUrl() }}" icon="heroicon-o-plus">
            Nueva asignación masiva
        </x-filament::button>
    </div>
</x-filament-panels::page>
