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
                // Es solo un enlace de navegación (sin ->action(), no escribe
                // nada) -- la propia página de destino ya se protege con
                // View:AsignarTurnos (HasPageShield) y AsignarMasivo:AsignarTurnos
                // para el envío real. Esto solo evita mostrar un atajo a una
                // pantalla a la que el usuario no podría entrar de todas formas.
                ->visible(fn () => \App\Filament\Pages\AsignarTurnos::canAccess())
                ->url(fn () => \App\Filament\Pages\AsignarTurnos::getUrl()),
            CreateAction::make()
                ->label('Asignar a un colaborador'),
        ];
    }
}
