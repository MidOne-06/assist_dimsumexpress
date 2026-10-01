<?php

namespace App\Filament\Resources\Empresas;

use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Models\Empresa;
use App\Rules\RucPeru;
use App\Services\EmpresaService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;
    protected static ?string $slug = 'empresas';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static string|\UnitEnum|null $navigationGroup = 'Organización';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Empresas';
    protected static ?string $modelLabel = 'empresa';
    protected static ?string $pluralModelLabel = 'empresas';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('nombre')->label('Razón social')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('codigo')->label('Código')->required()->maxLength(30)->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::upper(trim($state)) : null),
            TextInput::make('ruc')->label('RUC')->tel()->inputMode('numeric')->maxLength(11)->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? preg_replace('/\D/', '', $state) : null)->rules([new RucPeru]),
            Toggle::make('activo')->label('Activo')->default(true)->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nombre')->label('Razón social')->searchable()->sortable(),
            TextColumn::make('codigo')->label('Código')->badge()->searchable()->sortable(),
            TextColumn::make('ruc')->label('RUC')->placeholder('—')->searchable(),
            TextColumn::make('colaboradores_count')->label('Colaboradores')->counts('colaboradores')->sortable(),
            TextColumn::make('marcaciones_count')->label('Marcaciones')->counts('marcaciones')->sortable()->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('activo')->label('Activo')->boolean(),
        ])->filters([
            TernaryFilter::make('activo')->label('Activo'),
        ])->recordActions([
            EditAction::make()
                ->modal()
                ->modalHeading('Actualizar empresa')
                ->modalWidth(Width::Large)
                ->using(fn (Empresa $record, array $data) => app(EmpresaService::class)
                    ->actualizar(auth()->user(), $record, $data)),
        ])->paginated([10, 25, 50])->defaultPaginationPageOption(25)->toolbarActions([])->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return ['index' => ListEmpresas::route('/')];
    }
}
