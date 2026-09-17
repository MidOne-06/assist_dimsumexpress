<?php

namespace App\Filament\Resources\AsignacionTurnos\Pages;

use App\Filament\Resources\AsignacionTurnos\AsignacionTurnoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAsignacionTurnos extends ListRecords
{
    protected static string $resource = AsignacionTurnoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('asignarMasivo')
                ->label('Asignación masiva')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(fn () => \App\Filament\Pages\AsignarTurnos::getUrl()),
            CreateAction::make()
                ->label('Asignar a un colaborador'),
        ];
    }
}
