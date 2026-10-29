<x-filament-panels::page>
    <style>
        .fi-ta-table thead th:not(:first-child), .fi-ta-table tbody td:not(:first-child) { min-width: 9rem; }
        .fi-ta-table thead th:first-child, .fi-ta-table tbody td:first-child { min-width: 17rem; }
        .attendance-matrix-empty { color: var(--gray-400, #9ca3af); font-size: .75rem; }
        .attendance-matrix-mark { display: flex; align-items: center; justify-content: space-between; gap: .4rem; min-width: 7.5rem; padding: .2rem .35rem; border-radius: .375rem; font-size: .7rem; line-height: 1.15; }
        .attendance-matrix-mark + .attendance-matrix-mark { margin-top: .25rem; }
        .attendance-matrix-mark time { font-variant-numeric: tabular-nums; font-weight: 700; }
        .attendance-matrix-mark--success { background: color-mix(in srgb, var(--success-500, #22c55e) 12%, transparent); color: var(--success-700, #15803d); }
        .attendance-matrix-mark--warning { background: color-mix(in srgb, var(--warning-500, #f59e0b) 12%, transparent); color: var(--warning-700, #b45309); }
        .attendance-matrix-mark--info { background: color-mix(in srgb, var(--info-500, #3b82f6) 12%, transparent); color: var(--info-700, #1d4ed8); }
        .attendance-matrix-mark--danger { background: color-mix(in srgb, var(--danger-500, #ef4444) 12%, transparent); color: var(--danger-700, #b91c1c); }
        .attendance-matrix-mark--gray { background: var(--gray-100, #f3f4f6); color: var(--gray-700, #374151); }
        .attendance-matrix-day-header--today { background: color-mix(in srgb, var(--primary-500, #0ea5e9) 12%, transparent); }
        .attendance-matrix-day-cell--today { background: color-mix(in srgb, var(--primary-500, #0ea5e9) 4%, transparent); }
        .dark .attendance-matrix-mark--success { color: var(--success-300, #86efac); }
        .dark .attendance-matrix-mark--warning { color: var(--warning-300, #fcd34d); }
        .dark .attendance-matrix-mark--info { color: var(--info-300, #93c5fd); }
        .dark .attendance-matrix-mark--danger { color: var(--danger-300, #fca5a5); }
        .dark .attendance-matrix-mark--gray { background: var(--gray-800, #1f2937); color: var(--gray-300, #d1d5db); }
    </style>

    {{ $this->table }}
</x-filament-panels::page>
