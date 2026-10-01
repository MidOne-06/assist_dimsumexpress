<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Colaboradors\ColaboradorResource;
use App\Filament\Resources\Marcacions\MarcacionResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Resumen';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('crearColaborador')
                ->label('Crear colaborador')
                ->icon(Heroicon::OutlinedUserPlus)
                ->url(ColaboradorResource::getUrl('index', ['action' => 'create']))
                ->visible(fn (): bool => auth()->user()?->can('Create:Colaborador') ?? false),
            Action::make('asignarTurnos')
                ->label('Asignar turnos')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(AsignarTurnos::getUrl())
                ->visible(fn (): bool => auth()->user()?->can('View:AsignarTurnos') ?? false),
            Action::make('estacionesQr')
                ->label('Estaciones QR')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(EstacionesQr::getUrl())
                ->visible(fn (): bool => EstacionesQr::canAccess()),
            Action::make('marcaciones')
                ->label('Marcaciones')
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('gray')
                ->url(MarcacionResource::getUrl('index'))
                ->visible(fn (): bool => auth()->user()?->can('ViewAny:Marcacion') ?? false),
        ];
    }
}
