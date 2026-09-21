<x-filament-panels::page>
    <x-filament::section
        icon="heroicon-o-information-circle"
        icon-color="warning"
        heading="Uso para estaciones físicas"
        description="Cada enlace contiene una clave privada de estación. Ábrelo solamente en la pantalla asignada; desde allí se muestra un QR de asistencia que se renueva cada 20 segundos."
    />

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->estaciones as $estacion)
            <x-filament::section
                x-data="{ copiado: false }"
                icon="heroicon-o-qr-code"
                :heading="$estacion['nombre']"
                :description="$estacion['tipo'].' · '.$estacion['ubicacion']"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge color="success">Activa</x-filament::badge>
                </x-slot>
                <div class="flex justify-center bg-gray-50 p-5 dark:bg-white/5">
                    <img src="{{ $estacion['qr'] }}" alt="QR de acceso a la estación {{ $estacion['nombre'] }}" class="h-48 w-48 rounded-lg bg-white p-2">
                </div>

                <div class="mt-4 space-y-3">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="enlace-{{ $loop->index }}">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Enlace de estación</span>
                    </label>
                    <x-filament::input
                        id="enlace-{{ $loop->index }}"
                        type="text"
                        readonly
                        x-ref="enlace"
                        value="{{ $estacion['url'] }}"
                        onclick="this.select()"
                        class="w-full text-xs"
                    />
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            type="button"
                            icon="heroicon-o-clipboard-document"
                            x-on:click="navigator.clipboard.writeText($refs.enlace.value); copiado = true; setTimeout(() => copiado = false, 1500)"
                        >
                            <span x-show="!copiado">Copiar enlace</span>
                            <span x-show="copiado" x-cloak>Copiado</span>
                        </x-filament::button>
                        <x-filament::button
                            tag="a"
                            color="gray"
                            outlined
                            icon="heroicon-o-arrow-top-right-on-square"
                            :href="$estacion['url']"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Abrir estación
                        </x-filament::button>
                        <x-filament::button
                            tag="a"
                            color="gray"
                            outlined
                            icon="heroicon-o-arrow-down-tray"
                            :href="$estacion['qr']"
                            :download="'estacion-'.\Illuminate\Support\Str::slug($estacion['ubicacion'].'-'.$estacion['nombre']).'.svg'"
                        >
                            Descargar QR
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section heading="No hay estaciones disponibles">
                <p class="text-sm text-gray-600 dark:text-gray-300">No hay estaciones activas disponibles con tus permisos.</p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
