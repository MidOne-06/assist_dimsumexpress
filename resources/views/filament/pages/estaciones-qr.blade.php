<x-filament-panels::page>
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <strong>Uso para estaciones físicas.</strong>
        Cada enlace contiene una clave privada de estación. Ábrelo solamente en la pantalla asignada; desde allí se muestra un QR de asistencia que se renueva cada 20 segundos.
    </div>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->estaciones as $estacion)
            <section x-data="{ copiado: false }" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 p-4 dark:border-white/10">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $estacion['tipo'] }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $estacion['nombre'] }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $estacion['ubicacion'] }}</p>
                    </div>
                    <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">Activa</span>
                </div>

                <div class="flex justify-center bg-gray-50 p-5 dark:bg-white/5">
                    <img src="{{ $estacion['qr'] }}" alt="QR de acceso a la estación {{ $estacion['nombre'] }}" class="h-48 w-48 rounded-lg bg-white p-2">
                </div>

                <div class="space-y-3 p-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Enlace de estación</label>
                    <input
                        type="text"
                        readonly
                        x-ref="enlace"
                        value="{{ $estacion['url'] }}"
                        onclick="this.select()"
                        class="fi-input block w-full rounded-lg border-gray-300 text-xs dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            x-on:click="navigator.clipboard.writeText($refs.enlace.value); copiado = true; setTimeout(() => copiado = false, 1500)"
                            class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
                        >
                            <span x-show="!copiado">Copiar enlace</span>
                            <span x-show="copiado" x-cloak>Copiado</span>
                        </button>
                        <a href="{{ $estacion['url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            Abrir estación
                        </a>
                        <a href="{{ $estacion['qr'] }}" download="estacion-{{ \Illuminate\Support\Str::slug($estacion['ubicacion'].'-'.$estacion['nombre']) }}.svg" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            Descargar QR
                        </a>
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
                No hay estaciones activas disponibles con tus permisos.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
