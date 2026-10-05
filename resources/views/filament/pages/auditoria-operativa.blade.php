<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section compact>
            <x-slot name="heading">Bloqueos de marcación</x-slot>
            <div class="text-2xl font-semibold tracking-tight text-danger-600 dark:text-danger-400">
                {{ $this->resumen['criticos'] }}
            </div>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Revisión requerida</x-slot>
            <div class="text-2xl font-semibold tracking-tight text-warning-600 dark:text-warning-400">
                {{ $this->resumen['atencion'] }}
            </div>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Automatizaciones</x-slot>
            <div class="flex items-center justify-between gap-3">
                <x-filament::badge :color="$this->resumen['scheduler'] === 'Activo' ? 'success' : 'danger'">
                    {{ $this->resumen['scheduler'] }}
                </x-filament::badge>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $this->resumen['actualizado'] }}</span>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
