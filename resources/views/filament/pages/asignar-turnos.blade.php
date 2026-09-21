<x-filament-panels::page>
    <div class="mx-auto w-full max-w-5xl">
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
