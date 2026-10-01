<?php

namespace App\Filament\Pages;

use App\Services\AparienciaSistemaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AparienciaSistema extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;
    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Apariencia';
    protected static ?string $title = 'Apariencia del sistema';
    protected string $view = 'filament.pages.apariencia-sistema';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:AparienciaSistema') ?? false;
    }

    public function mount(): void
    {
        $ajuste = app(AparienciaSistemaService::class)->actual();

        $this->form->fill([
            'nombre_sistema' => $ajuste->nombre_sistema,
            'logo' => $ajuste->logo,
            'logo_oscuro' => $ajuste->logo_oscuro,
            'icono' => $ajuste->icono,
            'logo_app_movil' => $ajuste->logo_app_movil,
            'color_primario' => $ajuste->color_primario,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidad visual')
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextInput::make('nombre_sistema')
                            ->label('Nombre del sistema')
                            ->required()
                            ->maxLength(100)
                            ->autofocus()
                            ->columnSpan(['md' => 3]),
                        ColorPicker::make('color_primario')
                            ->label('Color principal')
                            ->hex()
                            ->hexColor()
                            ->required(),
                        FileUpload::make('logo')
                            ->label('Logo claro')
                            ->disk('public')
                            ->directory('marca')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->panelLayout('integrated')
                            ->panelAspectRatio('4:1')
                            ->imagePreviewHeight('72')
                            ->uploadButtonPosition('right')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadProgressIndicatorPosition('right')
                            ->openable()
                            ->columnSpan(['md' => 2]),
                        FileUpload::make('logo_oscuro')
                            ->label('Logo oscuro')
                            ->disk('public')
                            ->directory('marca')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->panelLayout('integrated')
                            ->panelAspectRatio('4:1')
                            ->imagePreviewHeight('72')
                            ->uploadButtonPosition('right')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadProgressIndicatorPosition('right')
                            ->openable()
                            ->columnSpan(['md' => 2]),
                        FileUpload::make('icono')
                            ->label('Ícono del navegador')
                            ->disk('public')
                            ->directory('marca')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['1:1'])
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->panelLayout('integrated')
                            ->panelAspectRatio('4:1')
                            ->imagePreviewHeight('72')
                            ->uploadButtonPosition('right')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadProgressIndicatorPosition('right')
                            ->openable()
                            ->columnSpan(['md' => 2]),
                        FileUpload::make('logo_app_movil')
                            ->label('Logo de app móvil')
                            ->disk('public')
                            ->directory('marca')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['1:1'])
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->panelLayout('integrated')
                            ->panelAspectRatio('4:1')
                            ->imagePreviewHeight('72')
                            ->uploadButtonPosition('right')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadProgressIndicatorPosition('right')
                            ->openable()
                            ->columnSpan(['md' => 2]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restablecer')
                ->label('Restablecer')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Restablecer apariencia')
                ->modalSubmitActionLabel('Restablecer')
                ->action('restablecer'),
            Action::make('guardar')
                ->label('Guardar cambios')
                ->action('guardar'),
        ];
    }

    public function guardar(): void
    {
        /** @var \App\Models\User $actor */
        $actor = auth()->user();
        $ajuste = app(AparienciaSistemaService::class)->actualizar($actor, $this->form->getState());

        $this->form->fill([
            'nombre_sistema' => $ajuste->nombre_sistema,
            'logo' => $ajuste->logo,
            'logo_oscuro' => $ajuste->logo_oscuro,
            'icono' => $ajuste->icono,
            'logo_app_movil' => $ajuste->logo_app_movil,
            'color_primario' => $ajuste->color_primario,
        ]);

        Notification::make()
            ->title('Cambios guardados')
            ->success()
            ->send();
    }

    public function restablecer(): void
    {
        /** @var \App\Models\User $actor */
        $actor = auth()->user();
        $ajuste = app(AparienciaSistemaService::class)->restablecer($actor);

        $this->form->fill([
            'nombre_sistema' => $ajuste->nombre_sistema,
            'logo' => $ajuste->logo,
            'logo_oscuro' => $ajuste->logo_oscuro,
            'icono' => $ajuste->icono,
            'logo_app_movil' => $ajuste->logo_app_movil,
            'color_primario' => $ajuste->color_primario,
        ]);

        Notification::make()
            ->title('Apariencia restablecida')
            ->success()
            ->send();
    }
}
