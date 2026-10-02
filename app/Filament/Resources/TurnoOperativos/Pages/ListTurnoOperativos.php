<?php

namespace App\Filament\Resources\TurnoOperativos\Pages;

use App\Filament\Resources\TurnoOperativos\TurnoOperativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListTurnoOperativos extends ListRecords
{
    protected static string $resource = TurnoOperativoResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->modal()->modalHeading('Habilitar turno por estación')->modalWidth(Width::Large)->createAnother(false)]; }
}
