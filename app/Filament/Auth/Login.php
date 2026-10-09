<?php

namespace App\Filament\Auth;

use App\Services\AparienciaSistemaService;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Enums\Width;

class Login extends BaseLogin
{
    protected Width|string|null $maxWidth = Width::Small;

    public function getTitle(): string
    {
        return app(AparienciaSistemaService::class)->nombre();
    }

    public function getHeading(): ?string
    {
        return null;
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Ingresar');
    }
}
