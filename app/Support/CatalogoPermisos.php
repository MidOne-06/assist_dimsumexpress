<?php

declare(strict_types=1);

namespace App\Support;

class CatalogoPermisos
{
    /**
     * Acciones que no corresponden a un CRUD ni a una página de Filament.
     * Las claves son los permisos persistidos; los valores, su etiqueta UI.
     *
     * @return array<string, array<string, string>>
     */
    public static function categorias(): array
    {
        return [
            'Acceso y seguridad' => [
                'Access:AdminPanel' => 'Acceso al panel administrativo',
                'ResetPassword:User' => 'Restablecer contraseñas',
            ],
            'Apariencia del sistema' => [
                'Gestionar:AparienciaSistema' => 'Gestionar identidad visual',
            ],
            'Auditoría operativa' => [
                'View:AuditoriaOperativa' => 'Consultar hallazgos operativos',
            ],
            'Marcación y supervisión' => [
                'Registrar:Marcacion' => 'Registrar asistencia',
                'View:MiHorario' => 'Ver mi horario',
                'Registrar:VisitaSupervisor' => 'Registrar visitas de supervisión',
                'View:CalendarioVisitasSupervisor' => 'Consultar calendario de visitas de supervisión',
                'View:ControlVisitasSupervisor' => 'Consultar control de visitas de supervisión',
                'Regularizar:VisitaSupervisor' => 'Regularizar salidas de visitas de supervisión',
                'Exportar:VisitaSupervisor' => 'Exportar visitas de supervisión',
                'Exportar:Marcacion' => 'Exportar marcaciones',
            ],
            'Turnos y cobertura' => [
                'AsignarMasivo:AsignarTurnos' => 'Asignar turnos en bloque',
                'Exportar:AsignacionTurno' => 'Exportar asignaciones de turno',
                'Regularizar:Jornada' => 'Regularizar jornadas con marcaciones sin turno',
                'Revisar:CoberturaOperativa' => 'Revisar coberturas operativas',
            ],
            'Estaciones QR' => [
                'VerEnlace:PuntoVenta' => 'Ver enlaces de estaciones',
                'RegenerarEnlace:PuntoVenta' => 'Regenerar enlaces de estaciones',
            ],
            'Incidencias' => [
                'Reportar:IncidenciaMarcacion' => 'Reportar incidencias de marcación',
                'Resolver:IncidenciaMarcacion' => 'Resolver incidencias de marcación',
            ],
            'Colaboradores' => [
                'Exportar:Colaborador' => 'Exportar colaboradores',
                'Importar:Colaborador' => 'Importar o actualizar colaboradores',
                'ViewEnlaces:Colaborador' => 'Ver historial de accesos',
                'GenerarEnlace:Colaborador' => 'Generar enlaces de acceso',
                'RevocarEnlace:Colaborador' => 'Revocar enlaces de acceso',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function nombres(): array
    {
        return array_values(array_merge(...array_values(array_map(
            static fn (array $permisos): array => array_keys($permisos),
            self::categorias(),
        ))));
    }
}
