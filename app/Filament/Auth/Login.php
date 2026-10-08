<?php

namespace App\Filament\Auth;

use App\Services\AparienciaSistemaService;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

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

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">Solo para gestión administrativa. <a class="font-semibold text-primary-600 dark:text-primary-400" href="' . e(route('login')) . '">Acceso de colaboradores y supervisores</a></span>');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Ingresar');
    }
}
