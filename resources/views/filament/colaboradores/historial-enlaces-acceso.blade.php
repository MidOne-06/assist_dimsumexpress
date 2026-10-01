@if ($enlaces->isEmpty())
    <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin enlaces registrados</div>
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-[42rem] divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
            <thead class="text-xs text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 font-medium">Estado</th>
                    <th class="px-3 py-2 font-medium">Generado</th>
                    <th class="px-3 py-2 font-medium">Vence</th>
                    <th class="px-3 py-2 font-medium">Usado</th>
                    <th class="px-3 py-2 font-medium">Generado por</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($enlaces as $enlace)
                    @php
                        [$estado, $color] = match (true) {
                            $enlace->usado_en !== null => ['Usado', 'success'],
                            $enlace->revocado_en !== null => ['Revocado', 'danger'],
                            $enlace->expira_en->isPast() => ['Vencido', 'warning'],
                            default => ['Vigente', 'info'],
                        };
                    @endphp
                    <tr>
                        <td class="px-3 py-3"><x-filament::badge :color="$color">{{ $estado }}</x-filament::badge></td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $enlace->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $enlace->expira_en->format('d/m/Y H:i:s') }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ $enlace->usado_en?->format('d/m/Y H:i:s') ?? '—' }}</td>
                        <td class="px-3 py-3">{{ $enlace->generadoPor?->name ?? 'Sistema' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
