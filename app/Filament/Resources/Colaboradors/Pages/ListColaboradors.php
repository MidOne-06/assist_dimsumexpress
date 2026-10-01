<?php

namespace App\Filament\Resources\Colaboradors\Pages;

use App\Actions\CrearColaborador;
use App\Filament\Resources\Colaboradors\ColaboradorResource;
use App\Services\ColaboradorSpreadsheetService;
use App\Support\PoliticaContrasena;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListColaboradors extends ListRecords
{
    protected static string $resource = ColaboradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarColaboradores')
                ->label('Exportar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->authorize(fn (): bool => auth()->user()?->can('Exportar:Colaborador') ?? false)
                ->action(fn () => app(ColaboradorSpreadsheetService::class)->exportar($this->getFilteredTableQuery())),
            Action::make('descargarPlantillaColaboradores')
                ->label('Plantilla')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->authorize(fn (): bool => auth()->user()?->can('Importar:Colaborador') ?? false)
                ->action(fn () => app(ColaboradorSpreadsheetService::class)->plantilla()),
            Action::make('importarColaboradores')
                ->label('Importar / actualizar')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->authorize(fn (): bool => auth()->user()?->can('Importar:Colaborador') ?? false)
                ->modalHeading('Importar colaboradores')
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel('Procesar archivo')
                ->schema([
                    FileUpload::make('archivo')
                        ->label('Archivo')
                        ->disk('local')
                        ->directory('importaciones-colaboradores')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                        ])
                        ->maxSize(5120)
                        ->required(),
                    TextInput::make('contrasena_inicial')
                        ->label('Contraseña inicial para nuevas cuentas')
                        ->password()
                        ->revealable()
                        ->minLength(PoliticaContrasena::MINIMO_CARACTERES)
                        ->rules([PoliticaContrasena::regla()]),
                ])
                ->action(function (array $data): void {
                    $archivo = $data['archivo'];

                    try {
                        $resultado = app(ColaboradorSpreadsheetService::class)->importar(
                            Storage::disk('local')->path($archivo),
                            auth()->user(),
                            $data['contrasena_inicial'] ?? null,
                        );
                    } finally {
                        Storage::disk('local')->delete($archivo);
                    }

                    if ($resultado['errores'] !== []) {
                        Notification::make()
                            ->title('No se realizaron cambios')
                            ->body(implode("\n", array_slice($resultado['errores'], 0, 5)))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Importación completada')
                        ->body("Creados: {$resultado['creados']}. Actualizados: {$resultado['actualizados']}.")
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->using(fn (array $data) => app(CrearColaborador::class)->handle($data, auth()->user()))
                ->modal()
                ->modalHeading('Crear colaborador')
                ->modalWidth(Width::ThreeExtraLarge)
                ->createAnother(false),
        ];
    }
}
