<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AjusteSistema;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AparienciaSistemaService
{
    public function actual(): AjusteSistema
    {
        return AjusteSistema::query()->firstOrCreate(
            ['id' => 1],
            [
                'nombre_sistema' => 'Sistema de Asistencias',
                'color_primario' => '#f59e0b',
                'revision_identidad' => 1,
            ],
        );
    }

    public function nombre(): string
    {
        return $this->actual()->nombre_sistema;
    }

    public function logoUrl(): string
    {
        $ajuste = $this->actual();

        return $this->archivoUrl($ajuste->logo, 'images/sistema-asistencias.svg', $ajuste);
    }

    public function logoOscuroUrl(): string
    {
        $ajuste = $this->actual();

        return $this->archivoUrl($ajuste->logo_oscuro ?? $ajuste->logo, 'images/sistema-asistencias.svg', $ajuste);
    }

    public function iconoUrl(): string
    {
        $ajuste = $this->actual();

        return $this->archivoUrl($ajuste->icono ?? $ajuste->logo_app_movil ?? $ajuste->logo, 'images/sistema-asistencias.svg', $ajuste);
    }

    /** Logo cuadrado usado por la PWA al instalarse en un teléfono. */
    public function logoAppMovilUrl(): string
    {
        $ajuste = $this->actual();

        return $this->archivoUrl($this->logoAppMovilArchivo($ajuste), 'images/sistema-asistencias.svg', $ajuste);
    }

    /** Tipo real del ícono publicado por el manifiesto PWA. */
    public function logoAppMovilMimeType(): string
    {
        $archivo = $this->logoAppMovilArchivo($this->actual());

        return match (strtolower(pathinfo(is_string($archivo) ? $archivo : '', PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'png' => 'image/png',
            default => 'image/svg+xml',
        };
    }

    public function colorPrimario(): string
    {
        $color = $this->actual()->color_primario;

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '#f59e0b';
    }

    private function archivoUrl(mixed $archivo, string $respaldo, AjusteSistema $ajuste): string
    {
        $url = null;

        if (is_string($archivo) && $archivo !== '' && Storage::disk('public')->exists($archivo)) {
            $url = Storage::disk('public')->url($archivo);
        }

        $url ??= asset($respaldo);

        // El nombre físico del archivo puede repetirse en una actualización.
        // La revisión hace que favicon, manifest y PWA vuelvan a solicitar la
        // identidad recién guardada, sin depender de vaciar cachés del celular.
        $revision = max(1, (int) ($ajuste->revision_identidad ?? 1));

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $revision;
    }

    private function logoAppMovilArchivo(AjusteSistema $ajuste): mixed
    {
        return $ajuste->logo_app_movil ?? $ajuste->logo;
    }

    /** @param array{nombre_sistema?: mixed, logo?: mixed, logo_oscuro?: mixed, icono?: mixed, logo_app_movil?: mixed, color_primario?: mixed} $data */
    public function actualizar(User $actor, array $data): AjusteSistema
    {
        abort_unless($actor->can('Gestionar:AparienciaSistema'), 403);

        $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombre_sistema'] ?? '')));

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw ValidationException::withMessages([
                'nombre_sistema' => 'Ingresa un nombre de hasta 100 caracteres.',
            ]);
        }

        $ajuste = $this->actual();
        $logo = $this->archivoValidado($data, 'logo', $ajuste->logo);
        $logoOscuro = $this->archivoValidado($data, 'logo_oscuro', $ajuste->logo_oscuro);
        $icono = $this->archivoValidado($data, 'icono', $ajuste->icono);
        $logoAppMovil = $this->archivoValidado($data, 'logo_app_movil', $ajuste->logo_app_movil);
        $color = strtolower(trim((string) ($data['color_primario'] ?? $ajuste->color_primario ?? '#f59e0b')));

        if (! preg_match('/^#[0-9a-f]{6}$/', $color)) {
            throw ValidationException::withMessages([
                'color_primario' => 'Selecciona un color válido.',
            ]);
        }

        $archivosAnteriores = [$ajuste->logo, $ajuste->logo_oscuro, $ajuste->icono, $ajuste->logo_app_movil];
        $archivosNuevos = [$logo, $logoOscuro, $icono, $logoAppMovil];

        DB::transaction(function () use ($ajuste, $nombre, $logo, $logoOscuro, $icono, $logoAppMovil, $color): void {
            $ajuste->forceFill([
                'nombre_sistema' => $nombre,
                'logo' => $logo,
                'logo_oscuro' => $logoOscuro,
                'icono' => $icono,
                'logo_app_movil' => $logoAppMovil,
                'color_primario' => $color,
                'revision_identidad' => ((int) ($ajuste->revision_identidad ?? 1)) + 1,
            ])->save();
        });

        $this->eliminarArchivosReemplazados($archivosAnteriores, $archivosNuevos);

        Log::notice('Identidad visual actualizada.', [
            'actor_user_id' => $actor->id,
            'logo_actualizado' => $archivosAnteriores !== $archivosNuevos,
        ]);

        return $ajuste->fresh();
    }

    public function restablecer(User $actor): AjusteSistema
    {
        abort_unless($actor->can('Gestionar:AparienciaSistema'), 403);

        $ajuste = $this->actual();
        $archivos = [$ajuste->logo, $ajuste->logo_oscuro, $ajuste->icono, $ajuste->logo_app_movil];

        $ajuste->forceFill([
            'nombre_sistema' => 'Sistema de Asistencias',
            'logo' => null,
            'logo_oscuro' => null,
            'icono' => null,
            'logo_app_movil' => null,
            'color_primario' => '#f59e0b',
            'revision_identidad' => ((int) ($ajuste->revision_identidad ?? 1)) + 1,
        ])->save();

        $this->eliminarArchivosReemplazados($archivos, []);

        Log::notice('Identidad visual restablecida.', ['actor_user_id' => $actor->id]);

        return $ajuste->fresh();
    }

    /** @param array<string, mixed> $data */
    private function archivoValidado(array $data, string $campo, mixed $actual): ?string
    {
        $archivo = array_key_exists($campo, $data) ? $data[$campo] : $actual;

        if ($archivo === null) {
            return null;
        }

        if (! is_string($archivo) || ! str_starts_with($archivo, 'marca/')) {
            throw ValidationException::withMessages([$campo => 'El archivo cargado no es válido.']);
        }

        return $archivo;
    }

    /** @param array<int, mixed> $anteriores @param array<int, mixed> $nuevos */
    private function eliminarArchivosReemplazados(array $anteriores, array $nuevos): void
    {
        collect($anteriores)
            ->filter(fn (mixed $archivo): bool => is_string($archivo) && str_starts_with($archivo, 'marca/') && ! in_array($archivo, $nuevos, true))
            ->unique()
            ->each(fn (string $archivo) => Storage::disk('public')->delete($archivo));
    }
}
