<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Services\EmpresaService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal()
                ->modalHeading('Crear empresa')
                ->modalWidth(Width::Large)
                ->createAnother(false)
                ->using(fn (array $data) => app(EmpresaService::class)->crear(auth()->user(), $data)),
        ];
    }
}
