<x-filament-panels::page>
    <x-filament::section
        icon="heroicon-o-information-circle"
        icon-color="warning"
        heading="Uso para estaciones físicas"
        description="Cada QR abre la estación de su local. La estación muestra el QR temporal de asistencia, que se renueva cada 20 segundos."
    />

    {{ $this->table }}
</x-filament-panels::page>
