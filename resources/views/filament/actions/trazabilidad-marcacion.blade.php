@php
    $etiquetas = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'salida_refrigerio' => 'Salida a refrigerio',
        'regreso_refrigerio' => 'Regreso de refrigerio',
    ];
    $retorno = $marcacion->resumenRetornoRefrigerio();
@endphp

<div class="space-y-5 text-sm">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Marcación</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $etiquetas[$marcacion->tipo] ?? $marcacion->tipo }}</div>
            <div class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $marcacion->fecha_hora->format('d/m/Y H:i:s') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Colaborador</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $marcacion->colaborador?->nombre_completo ?? '—' }}</div>
            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $marcacion->colaborador?->documento_identidad ?? 'Sin documento' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Referencia</div>
            <div class="mt-1 font-mono font-semibold text-gray-950 dark:text-white">#{{ $marcacion->id }}</div>
            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">Creado: {{ $marcacion->created_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
        </div>
    </div>

    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Turno</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $marcacion->turno?->nombre ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Estación</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $marcacion->sucursal?->nombre ?? '—' }}{{ $marcacion->puntoVenta ? ' · ' . $marcacion->puntoVenta->nombre : '' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">QR dinámico</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $marcacion->qrToken ? 'QR #' . $marcacion->qrToken->id : 'No disponible' }}</dd>
            @if ($marcacion->qrToken)
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $marcacion->qrToken->proposito }} · vence {{ $marcacion->qrToken->expira_en->format('d/m/Y H:i:s') }}</div>
            @endif
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Origen</dt>
            <dd class="mt-1 font-mono font-medium text-gray-950 dark:text-white">{{ $marcacion->ip_origen ?? '—' }}</dd>
        </div>
    </dl>

    @if ($retorno)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="font-medium text-amber-950 dark:text-amber-100">Control de refrigerio: {{ $retorno['etiqueta'] }}</div>
            <div class="mt-1 text-xs text-amber-800 dark:text-amber-200">Retorno esperado: {{ $retorno['esperado']->format('d/m/Y H:i:s') }} · diferencia: {{ $marcacion->refrigerio_diferencia_segundos }} segundos</div>
        </div>
    @endif

    <div>
        <div class="text-xs text-gray-500 dark:text-gray-400">Agente del dispositivo</div>
        <div class="mt-1 break-all rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-700 dark:bg-white/5 dark:text-gray-200">{{ $marcacion->user_agent ?? '—' }}</div>
    </div>
</div>
