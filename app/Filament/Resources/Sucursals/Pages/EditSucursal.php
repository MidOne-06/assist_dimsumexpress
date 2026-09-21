<?php

namespace App\Filament\Resources\Sucursals\Pages;

use App\Filament\Concerns\HasCompactFormWidth;
use App\Filament\Resources\Sucursals\SucursalResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSucursal extends EditRecord
{
    use HasCompactFormWidth;

    protected static string $resource = SucursalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if ($this->record->puntosVenta()->exists() || $this->record->colaboradores()->exists()) {
                        Notification::make()
                            ->title('No se puede eliminar')
                            ->body('Esta sucursal tiene puntos de venta o colaboradores asociados. Reasígnalos primero.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
