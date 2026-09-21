<?php

namespace App\Filament\Resources\PuntoVentas\Pages;

use App\Filament\Concerns\HasCompactFormWidth;
use App\Filament\Resources\PuntoVentas\PuntoVentaResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPuntoVenta extends EditRecord
{
    use HasCompactFormWidth;

    protected static string $resource = PuntoVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if ($this->record->colaboradores()->exists()) {
                        Notification::make()
                            ->title('No se puede eliminar')
                            ->body('Este punto de venta tiene colaboradores asignados. Reasígnalos primero.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
