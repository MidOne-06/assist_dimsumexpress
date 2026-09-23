<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return 'Administración — ' . config('app.name');
    }

    public function getHeading(): ?string
    {
        return 'Administración';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Ingresar');
    }
}
