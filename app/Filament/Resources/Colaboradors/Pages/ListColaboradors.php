<?php

namespace App\Filament\Resources\Colaboradors\Pages;

use App\Filament\Resources\Colaboradors\ColaboradorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListColaboradors extends ListRecords
{
    protected static string $resource = ColaboradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal()
                ->modalHeading('Crear colaborador')
                ->modalWidth(Width::TwoExtraLarge)
                ->createAnother(false),
        ];
    }
}
