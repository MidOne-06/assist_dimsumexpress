<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): User {
                    /** @var User $actor */
                    $actor = auth()->user();

                    return app(UserService::class)->crear($actor, $data);
                })
                ->modal()
                ->modalHeading('Crear usuario')
                ->modalWidth(Width::ExtraLarge)
                ->createAnother(false),
        ];
    }
}
