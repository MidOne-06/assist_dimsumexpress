@php
    $url = $url ?? $estacion['url'];
    $etiqueta = $etiqueta ?? 'Enlace de estación';
    $archivo = $archivo ?? 'estacion-';
    $mostrarEnlace = $mostrarEnlace ?? true;
    $permitirAbrir = $permitirAbrir ?? true;
@endphp
<div x-data="{ copiado: false }" class="space-y-4">
    <x-filament::section
        icon="heroicon-o-qr-code"
        :heading="$estacion['nombre']"
        :description="$estacion['tipo_label'].' · '.$estacion['sucursal']"
    >
        <div class="flex justify-center bg-gray-50 p-4 dark:bg-white/5">
            <img src="{{ $qr }}" alt="QR de acceso a la estación {{ $estacion['nombre'] }}" class="h-56 w-56 rounded-lg bg-white p-2">
        </div>

        <div class="mt-4 space-y-3">
            @if ($mostrarEnlace)
                <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="enlace-estacion-modal">
                    <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $etiqueta }}</span>
                </label>
                <x-filament::input
                    id="enlace-estacion-modal"
                    type="text"
                    readonly
                    x-ref="enlace"
                    value="{{ $url }}"
                    onclick="this.select()"
                    class="w-full text-xs"
                />
            @endif
            <div class="flex flex-wrap gap-2">
                @if ($mostrarEnlace)
                    <x-filament::button icon="heroicon-o-clipboard-document" x-on:click="navigator.clipboard.writeText($refs.enlace.value); copiado = true; setTimeout(() => copiado = false, 1500)">
                        <span x-show="!copiado">Copiar enlace</span>
                        <span x-show="copiado" x-cloak>Copiado</span>
                    </x-filament::button>
                @endif
                @if ($permitirAbrir)
                    <x-filament::button tag="a" color="gray" outlined icon="heroicon-o-arrow-top-right-on-square" :href="$url" target="_blank" rel="noopener noreferrer">
                        Abrir estación
                    </x-filament::button>
                @endif
                <x-filament::button tag="a" color="gray" outlined icon="heroicon-o-arrow-down-tray" :href="$qr" :download="$archivo.\Illuminate\Support\Str::slug($estacion['sucursal'].'-'.$estacion['nombre']).'.svg'">
                    Descargar QR
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</div>
