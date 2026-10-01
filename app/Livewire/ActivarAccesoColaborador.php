<?php

namespace App\Livewire;

use App\Models\EnlaceAccesoColaborador;
use App\Services\EnlacesAccesoColaboradorService;
use App\Support\PoliticaContrasena;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ActivarAccesoColaborador extends Component implements HasForms
{
    use InteractsWithForms;

    public string $token;

    public bool $disponible = false;

    public ?string $nombreColaborador = null;

    public ?string $venceA = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(string $token, EnlacesAccesoColaboradorService $enlaces): void
    {
        $this->token = $token;

        $enlace = $this->enlaceVigente($enlaces);

        if (! $enlace) {
            return;
        }

        $this->disponible = true;
        $this->nombreColaborador = $enlace->colaborador->nombre_completo;
        $this->venceA = $enlace->expira_en->format('H:i');

        $this->form->fill([
            'email' => $enlace->user->email,
            'recordar' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->extraInputAttributes(['id' => 'access-password'])
                    ->required()
                    ->rules([PoliticaContrasena::regla()]),
                TextInput::make('password_confirmation')
                    ->label('Confirmar contraseña')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->extraInputAttributes(['id' => 'access-password-confirmation'])
                    ->required()
                    ->same('password'),
                Checkbox::make('recordar')
                    ->label('Mantener sesión iniciada'),
            ])
            ->statePath('data');
    }

    public function activar(): mixed
    {
        $data = $this->form->getState();

        $enlace = DB::transaction(function (): ?EnlaceAccesoColaborador {
            $enlace = EnlaceAccesoColaborador::query()
                ->with(['colaborador', 'user'])
                ->where('token_hash', hash('sha256', $this->token))
                ->lockForUpdate()
                ->first();

            if (! $this->puedeUsarse($enlace)) {
                return null;
            }

            $enlace->update([
                'usado_en' => now(),
                'usado_desde_ip' => request()->ip(),
                'user_agent_uso' => substr((string) request()->userAgent(), 0, 1000),
            ]);

            return $enlace;
        });

        if (! $enlace) {
            $this->disponible = false;
            $this->addError('data.password', 'El enlace ya no está disponible. Solicita uno nuevo.');

            return null;
        }

        $enlace->user->restablecerContrasena($data['password']);

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        Auth::login($enlace->user->fresh(), (bool) ($data['recordar'] ?? false));
        request()->session()->regenerate();
        request()->session()->put('acceso_operativo_via_enlace', true);

        return $this->redirectRoute('marcacion.show', navigate: true);
    }

    public function render()
    {
        return view('livewire.activar-acceso-colaborador');
    }

    private function enlaceVigente(EnlacesAccesoColaboradorService $enlaces): ?EnlaceAccesoColaborador
    {
        $enlace = $enlaces->buscar($this->token);

        return $this->puedeUsarse($enlace) ? $enlace : null;
    }

    private function puedeUsarse(?EnlaceAccesoColaborador $enlace): bool
    {
        return $enlace?->estaVigente() === true
            && $enlace->colaborador?->activo === true
            && $enlace->user?->estaActivoParaAcceso() === true
            && $enlace->user?->can('Registrar:Marcacion') === true;
    }
}
