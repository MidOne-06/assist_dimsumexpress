<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use App\Services\RoleService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): \Spatie\Permission\Models\Role {
                    /** @var User $actor */
                    $actor = auth()->user();

                    return app(RoleService::class)->crear($actor, $data);
                })
                ->modal()
                ->modalHeading('Crear rol')
                ->modalWidth(Width::FiveExtraLarge)
                ->createAnother(false),
        ];
    }
}
