<?php

namespace App\Filament\Resources\Turnos\Pages;

use App\Filament\Resources\Turnos\TurnoResource;
use App\Models\Turno;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTurno extends EditRecord
{
    protected static string $resource = TurnoResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Turno $record */
        return $record->actualizarParaFuturo($data);
    }

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
