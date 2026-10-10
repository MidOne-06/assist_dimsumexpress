<?php

namespace App\Filament\Resources\Marcacions\Tables;

use App\Models\AsignacionTurno;
use App\Models\CoberturaOperativa;
use App\Models\IncidenciaMarcacion;
use App\Models\Marcacion;
use App\Support\JornadaMarcacion;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/** Esquemas de los modales administrativos de jornada y trazabilidad. */
final class MarcacionInfolistSchemas
{
    /** @return array<Section> */
    public static function jornada(Marcacion $marcacion): array
    {
        $asignacion = self::asignacionDeMarcacion($marcacion);

        if ($asignacion === null || $marcacion->colaborador === null) {
            return [
                Section::make()
                    ->compact()
                    ->schema([
                        TextEntry::make('estado')->label('Estado')->state('Sin asignación de turno vinculada')->badge()->color('warning'),
                    ]),
            ];
        }

        $marcaciones = JornadaMarcacion::marcaciones($marcacion->colaborador, $asignacion);
        $resumen = JornadaMarcacion::resumen($marcacion->colaborador, $asignacion, $marcaciones);
        $limites = JornadaMarcacion::limites($asignacion);
        $entrada = $marcaciones->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
        $salidaRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_SALIDA_REFRIGERIO);
        $regresoRefrigerio = $marcaciones->firstWhere('tipo', Marcacion::TIPO_REGRESO_REFRIGERIO);
        $salida = $marcaciones->filter(fn (Marcacion $evento): bool => $evento->tipo === Marcacion::TIPO_SALIDA)->last();
        $ultima = $marcaciones->last();
        $incidencias = IncidenciaMarcacion::query()
            ->where('asignacion_turno_id', $asignacion->id)
            ->orderBy('detectada_en')
            ->get();

        $estado = self::estadoJornada($resumen['estado'], $ultima?->tipo);
        $cobertura = $marcacion->coberturaOperativa;

        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('estado')->label('Estado')->state($estado['etiqueta'])->badge()->color($estado['color']),
                    TextEntry::make('turno')->label('Turno')->state($asignacion->turno?->nombre ?? '—'),
                    TextEntry::make('programado')->label('Programado')->state($limites['inicio']->format('d/m/Y H:i:s').' · '.$limites['fin']->format('H:i:s')),
                ]),
            Section::make('Marcaciones')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('entrada')->label('Entrada')->state($entrada?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('salida')->label('Salida')->state($salida?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('salida_refrigerio')->label('Salida a refrigerio')->state($salidaRefrigerio?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('regreso_refrigerio')->label('Regreso de refrigerio')->state($regresoRefrigerio?->fecha_hora)->dateTime('d/m/Y H:i:s')->placeholder('—'),
                ]),
            Section::make('Horas')
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('efectivas')->label('Efectivas')->state(self::formatearSegundos($resumen['efectivos_segundos'])),
                    TextEntry::make('objetivo')->label('Objetivo')->state(self::formatearSegundos($resumen['objetivo_segundos'])),
                    TextEntry::make('diferencia')->label('Diferencia')->state(self::formatearDiferenciaSegundos($resumen['diferencia_segundos']))->badge()->color(($resumen['diferencia_segundos'] ?? 0) < 0 ? 'danger' : (($resumen['diferencia_segundos'] ?? 0) > 0 ? 'warning' : 'success')),
                ]),
            Section::make('Cumplimiento')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('entrada_estado')->label('Entrada')->state(self::estadoEntrada($entrada, $limites['inicio'], (int) $asignacion->turno->tolerancia_entrada_minutos)['etiqueta'])->badge()->color(self::estadoEntrada($entrada, $limites['inicio'], (int) $asignacion->turno->tolerancia_entrada_minutos)['color']),
                    TextEntry::make('salida_estado')->label('Salida')->state(self::estadoSalida($salida, $limites['fin'], (int) $asignacion->turno->tolerancia_salida_minutos)['etiqueta'])->badge()->color(self::estadoSalida($salida, $limites['fin'], (int) $asignacion->turno->tolerancia_salida_minutos)['color']),
                ]),
            Section::make('Estación')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('local')->label('Local marcado')->state(collect([$marcacion->sucursal?->nombre, $marcacion->puntoVenta?->nombre])->filter()->join(' · ') ?: '—'),
                    TextEntry::make('cobertura')->label('Cobertura')->state($cobertura ? self::etiquetaCobertura($cobertura->estado) : 'Local habitual')->badge()->color($cobertura ? match ($cobertura->estado) {
                        CoberturaOperativa::ESTADO_REVISADA => 'success',
                        CoberturaOperativa::ESTADO_OBSERVADA => 'danger',
                        default => 'warning',
                    } : 'gray'),
                ]),
            Section::make('Incidencias')
                ->compact()
                ->visible($incidencias->isNotEmpty())
                ->schema([
                    TextEntry::make('incidencias')->label('Registro')->state($incidencias->map(fn (IncidenciaMarcacion $incidencia): string => IncidenciaMarcacion::etiquetaTipo($incidencia->tipo).' · '.($incidencia->estaPendiente() ? 'Pendiente' : 'Resuelta'))->implode("\n"))->wrap(),
                ]),
        ];
    }

    private static function asignacionDeMarcacion(Marcacion $marcacion): ?AsignacionTurno
    {
        if ($marcacion->turno_id === null) {
            return null;
        }

        return AsignacionTurno::query()
            ->with('turno')
            ->where('colaborador_id', $marcacion->colaborador_id)
            ->where('turno_id', $marcacion->turno_id)
            ->whereIn('fecha', [$marcacion->fecha_hora->toDateString(), $marcacion->fecha_hora->copy()->subDay()->toDateString()])
            ->get()
            ->first(fn (AsignacionTurno $asignacion): bool => $marcacion->fecha_hora->betweenIncluded(
                JornadaMarcacion::limites($asignacion)['ventana_inicio'],
                JornadaMarcacion::limites($asignacion)['jornada_fin_maximo'],
            ));
    }

    /** @return array{etiqueta: string, color: string} */
    private static function estadoJornada(string $estado, ?string $ultimoTipo): array
    {
        if ($ultimoTipo === Marcacion::TIPO_SALIDA_REFRIGERIO) {
            return ['etiqueta' => 'En refrigerio', 'color' => 'warning'];
        }

        return match ($estado) {
            'cumplida' => ['etiqueta' => 'Cumplida', 'color' => 'success'],
            'extendida' => ['etiqueta' => 'Extendida', 'color' => 'warning'],
            'pendiente' => ['etiqueta' => 'Pendiente', 'color' => 'danger'],
            'inconsistente' => ['etiqueta' => 'Observada', 'color' => 'danger'],
            default => ['etiqueta' => 'En curso', 'color' => 'info'],
        };
    }

    private static function formatearMinutos(?int $minutos): string
    {
        return $minutos === null ? '—' : sprintf('%dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }

    private static function formatearDiferencia(?int $minutos): string
    {
        if ($minutos === null) {
            return 'En curso';
        }

        return ($minutos > 0 ? '+' : ($minutos < 0 ? '−' : '')).self::formatearMinutos(abs($minutos));
    }

    private static function formatearSegundos(?int $segundos): string
    {
        if ($segundos === null) {
            return '—';
        }

        $absoluto = abs($segundos);
        $horas = intdiv($absoluto, 3600);
        $minutos = intdiv($absoluto % 3600, 60);
        $restantes = $absoluto % 60;

        return "{$horas} h {$minutos} min" . ($restantes ? " {$restantes} s" : '');
    }

    private static function formatearDiferenciaSegundos(?int $segundos): string
    {
        if ($segundos === null) {
            return 'En curso';
        }

        return ($segundos > 0 ? '+' : ($segundos < 0 ? '−' : '')).self::formatearSegundos(abs($segundos));
    }

    /** @return array{etiqueta: string, color: string} */
    private static function estadoEntrada(?Marcacion $entrada, \Carbon\Carbon $inicio, int $tolerancia): array
    {
        if ($entrada === null) {
            return ['etiqueta' => 'Pendiente', 'color' => 'gray'];
        }

        $limite = $inicio->copy()->addMinutes($tolerancia);
        if ($entrada->fecha_hora->lte($limite)) {
            return ['etiqueta' => 'A tiempo', 'color' => 'success'];
        }

        return [
            'etiqueta' => self::formatearDuracionSegundos($entrada->fecha_hora->getTimestamp() - $limite->getTimestamp()).' tarde',
            'color' => 'danger',
        ];
    }

    /** @return array{etiqueta: string, color: string} */
    private static function estadoSalida(?Marcacion $salida, \Carbon\Carbon $fin, int $tolerancia): array
    {
        if ($salida === null) {
            return ['etiqueta' => 'Pendiente', 'color' => 'gray'];
        }

        $limite = $fin->copy()->subMinutes($tolerancia);
        if ($salida->fecha_hora->gte($limite)) {
            return ['etiqueta' => 'Conforme', 'color' => 'success'];
        }

        return [
            'etiqueta' => self::formatearDuracionSegundos($limite->getTimestamp() - $salida->fecha_hora->getTimestamp()).' antes',
            'color' => 'danger',
        ];
    }

    private static function formatearDuracionSegundos(int $segundos): string
    {
        $segundos = max(0, $segundos);
        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        $restantes = $segundos % 60;

        return collect([
            $horas > 0 ? $horas.' h' : null,
            $minutos > 0 ? $minutos.' min' : null,
            $restantes > 0 ? $restantes.' s' : null,
        ])->filter()->join(' ') ?: '0 s';
    }

    private static function etiquetaCobertura(string $estado): string
    {
        return match ($estado) {
            CoberturaOperativa::ESTADO_REVISADA => 'Cobertura conforme',
            CoberturaOperativa::ESTADO_OBSERVADA => 'Cobertura observada',
            default => 'Cobertura pendiente',
        };
    }

    /**
     * @return array<Section>
     */
    public static function trazabilidad(Marcacion $marcacion): array
    {
        $retorno = $marcacion->resumenRetornoRefrigerio();

        return [
            Section::make()
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('tipo')
                        ->label('Marcación')
                        ->state(self::etiquetaTipo($marcacion->tipo))
                        ->badge()
                        ->color(self::colorTipo($marcacion->tipo)),
                    TextEntry::make('fecha_hora')
                        ->label('Fecha y hora')
                        ->state($marcacion->fecha_hora)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('referencia')
                        ->label('Referencia')
                        ->state('#' . $marcacion->id),
                ]),
            Section::make('Colaborador')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('colaborador')
                        ->label('Nombre')
                        ->state($marcacion->colaborador?->nombre_completo ?? '—'),
                    TextEntry::make('documento')
                        ->label('Documento')
                        ->state($marcacion->colaborador?->documento_identidad ?? '—'),
                    TextEntry::make('empresa')
                        ->label('Empresa')
                        ->state($marcacion->empresa?->nombre ?? '—'),
                    TextEntry::make('area')
                        ->label('Área')
                        ->state($marcacion->area?->nombre ?? '—'),
                ]),
            Section::make('Estación y turno')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('estacion')
                        ->label('Estación')
                        ->state(collect([$marcacion->sucursal?->nombre, $marcacion->puntoVenta?->nombre])->filter()->join(' · ') ?: '—'),
                    TextEntry::make('turno')
                        ->label('Turno')
                        ->state($marcacion->turno?->nombre ?? '—'),
                    TextEntry::make('qr')
                        ->label('QR dinámico')
                        ->state($marcacion->qrToken ? 'Validado' : '—')
                        ->badge()
                        ->color($marcacion->qrToken ? 'success' : 'gray'),
                    TextEntry::make('expira_en')
                        ->label('Venció')
                        ->state($marcacion->qrToken?->expira_en)
                        ->dateTime('d/m/Y H:i:s')
                        ->placeholder('—'),
                ]),
            Section::make('Refrigerio')
                ->compact()
                ->columns(['default' => 1, 'md' => 3])
                ->visible($retorno !== null)
                ->schema([
                    TextEntry::make('retorno_estado')
                        ->label('Retorno')
                        ->state($retorno['etiqueta'] ?? '—')
                        ->badge()
                        ->color(match ($retorno['estado'] ?? null) {
                            'puntual' => 'success',
                            'temprano' => 'warning',
                            'tarde' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('retorno_esperado')
                        ->label('Retorno esperado')
                        ->state($retorno['esperado'] ?? null)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('diferencia_refrigerio')
                        ->label('Diferencia')
                        ->state($marcacion->refrigerio_diferencia_segundos === null ? '—' : $marcacion->refrigerio_diferencia_segundos . ' s'),
                ]),
            Section::make('Origen')
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('ip_origen')
                        ->label('IP')
                        ->state($marcacion->ip_origen ?? '—')
                        ->copyable(),
                    TextEntry::make('created_at')
                        ->label('Registrado')
                        ->state($marcacion->created_at)
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('user_agent')
                        ->label('Dispositivo')
                        ->state($marcacion->user_agent ?? '—')
                        ->wrap()
                        ->copyable()
                        ->columnSpanFull(),
                    TextEntry::make('cobertura')
                        ->label('Cobertura')
                        ->state($marcacion->coberturaOperativa ? self::etiquetaCobertura($marcacion->coberturaOperativa->estado) : 'Local habitual')
                        ->badge()
                        ->color($marcacion->coberturaOperativa ? match ($marcacion->coberturaOperativa->estado) {
                            CoberturaOperativa::ESTADO_REVISADA => 'success',
                            CoberturaOperativa::ESTADO_OBSERVADA => 'danger',
                            default => 'warning',
                        } : 'gray'),
                ]),
        ];
    }

    private static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'salida_refrigerio' => 'Salida a refrigerio',
            'regreso_refrigerio' => 'Regreso de refrigerio',
            default => $tipo,
        };
    }

    private static function colorTipo(string $tipo): string
    {
        return match ($tipo) {
            'entrada' => 'success',
            'salida' => 'danger',
            'salida_refrigerio' => 'warning',
            'regreso_refrigerio' => 'info',
            default => 'gray',
        };
    }
}
