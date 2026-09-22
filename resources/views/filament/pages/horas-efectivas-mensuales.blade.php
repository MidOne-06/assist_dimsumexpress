<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-filament::icon-button icon="heroicon-o-chevron-left" label="Mes anterior" wire:click="mesAnterior" />
                <span class="min-w-40 text-center text-sm font-semibold">
                    {{ ucfirst(\Illuminate\Support\Carbon::parse($mes . '-01')->locale('es')->translatedFormat('F Y')) }}
                </span>
                <x-filament::icon-button icon="heroicon-o-chevron-right" label="Mes siguiente" wire:click="mesSiguiente" />
                <x-filament::button color="gray" size="sm" wire:click="irAHoy">Hoy</x-filament::button>
            </div>

            <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-2 xl:grid-cols-3">
                <x-filament::input.wrapper>
                    <x-filament::input type="month" wire:model.live="mes" aria-label="Mes" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sucursalId" aria-label="Sucursal">
                        @forelse ($this->sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @empty
                            <option value="">Sin locales disponibles</option>
                        @endforelse
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="colaboradorId" aria-label="Colaborador">
                        <option value="">Todos los colaboradores</option>
                        @foreach ($this->colaboradores as $colaborador)
                            <option value="{{ $colaborador->id }}">{{ $colaborador->nombre_completo }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    @php
        $resumenes = $this->resumenes;
        $totalEfectivas = $resumenes->sum('efectivos_minutos');
        $totalObjetivo = $resumenes->sum('objetivo_minutos');
        $totalExtras = $resumenes->sum('extras_minutos');
    @endphp

    <x-filament::section>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Horas efectivas</div>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($totalEfectivas) }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Objetivo acumulado</div>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($totalObjetivo) }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Diferencia</div>
                <div class="mt-1 text-2xl font-semibold {{ $totalEfectivas - $totalObjetivo < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($totalEfectivas - $totalObjetivo) }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Horas extra</div>
                <div class="mt-1 text-2xl font-semibold text-warning-600 dark:text-warning-400">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($totalExtras) }}</div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full min-w-180 divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-3 font-medium">Colaborador</th>
                        <th class="px-3 py-3 font-medium">Local</th>
                        <th class="px-3 py-3 text-right font-medium">Jornadas</th>
                        <th class="px-3 py-3 text-right font-medium">Efectivas</th>
                        <th class="px-3 py-3 text-right font-medium">Objetivo</th>
                        <th class="px-3 py-3 text-right font-medium">Diferencia</th>
                        <th class="px-3 py-3 text-right font-medium">Extras</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($resumenes as $fila)
                        <tr class="text-gray-700 dark:text-gray-200">
                            <td class="px-3 py-3 font-medium">{{ $fila['colaborador']->nombre_completo }}</td>
                            <td class="px-3 py-3">{{ $fila['colaborador']->sucursal->nombre }}</td>
                            <td class="px-3 py-3 text-right">{{ $fila['jornadas_cerradas'] }}</td>
                            <td class="px-3 py-3 text-right font-medium">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($fila['efectivos_minutos']) }}</td>
                            <td class="px-3 py-3 text-right">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($fila['objetivo_minutos']) }}</td>
                            <td class="px-3 py-3 text-right {{ $fila['diferencia_minutos'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($fila['diferencia_minutos']) }}</td>
                            <td class="px-3 py-3 text-right text-warning-600 dark:text-warning-400">{{ \App\Filament\Pages\HorasEfectivasMensuales::formatoHoras($fila['extras_minutos']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Sin jornadas cerradas en este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
