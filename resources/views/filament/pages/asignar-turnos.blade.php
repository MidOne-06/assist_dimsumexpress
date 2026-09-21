<x-filament-panels::page>
    <div class="w-full" style="max-width: 56rem; margin-inline: auto;">
        <form wire:submit="asignar">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-calendar-days">
                    Asignar turno
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
