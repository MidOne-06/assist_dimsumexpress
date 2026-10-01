<?php

namespace App\Filament\Resources\Marcacions\Pages;

use App\Filament\Resources\Marcacions\MarcacionResource;
use App\Filament\Widgets\ResumenMarcaciones;
use App\Services\MarcacionSpreadsheetService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarcacions extends ListRecords
{
    protected static string $resource = MarcacionResource::class;

    // Sin CreateAction: las marcaciones solo se generan desde el flujo real
    // de QR (estación de marcado + celular del colaborador), nunca a mano desde el panel.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')
                ->label('Exportar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('Exportar:Marcacion') ?? false)
                ->authorize(fn (): bool => auth()->user()?->can('Exportar:Marcacion') ?? false)
                ->action(fn () => app(MarcacionSpreadsheetService::class)->exportar(
                    $this->getFilteredTableQuery()->clone(),
                )),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ResumenMarcaciones::class,
        ];
    }
}
