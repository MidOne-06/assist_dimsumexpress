<div x-data="{ copiado: false }" class="space-y-2">
    <div class="flex items-center gap-2">
        <input
            type="text"
            readonly
            x-ref="enlace"
            value="{{ $url }}"
            onclick="this.select()"
            class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
        />
        <button
            type="button"
            x-on:click="navigator.clipboard.writeText($refs.enlace.value); copiado = true; setTimeout(() => copiado = false, 1500)"
            class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
        >
            <span x-show="!copiado">Copiar</span>
            <span x-show="copiado" x-cloak>Copiado</span>
        </button>
    </div>
</div>
