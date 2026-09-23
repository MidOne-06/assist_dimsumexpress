<?php

namespace App\Filament\Resources\CoberturaOperativas\Pages;

use App\Filament\Resources\CoberturaOperativas\CoberturaOperativaResource;
use Filament\Resources\Pages\ListRecords;

class ListCoberturaOperativas extends ListRecords
{
    protected static string $resource = CoberturaOperativaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
