<?php

namespace App\Filament\Resources\Turnos\Pages;

use App\Filament\Concerns\HasCompactFormWidth;
use App\Filament\Resources\Turnos\TurnoResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTurno extends EditRecord
{
    use HasCompactFormWidth;

    protected static string $resource = TurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if ($this->record->asignaciones()->exists()) {
                        Notification::make()
                            ->title('No se puede eliminar')
                            ->body('Este turno tiene asignaciones en el calendario. Elimínalas primero o desactívalo en vez de borrarlo.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
