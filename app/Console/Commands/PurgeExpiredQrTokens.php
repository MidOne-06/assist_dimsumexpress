<?php

namespace App\Console\Commands;

use App\Models\QrToken;
use Illuminate\Console\Command;

class PurgeExpiredQrTokens extends Command
{
    protected $signature = 'qr:purge-expired {--hours=24 : Horas de retención después de vencer}';

    protected $description = 'Elimina QR vencidos sin ninguna referencia histórica de asistencia o visita';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $query = QrToken::query()
            ->where('expira_en', '<', now()->subHours($hours))
            ->whereDoesntHave('marcaciones')
            ->whereDoesntHave('visitasSupervisor')
            ->whereDoesntHave('visitasSupervisorIngreso')
            ->whereDoesntHave('visitasSupervisorSalida')
            ->whereDoesntHave('visitaSupervisorMarcaciones');

        $deleted = $query->delete();

        $this->info("QR vencidos eliminados: {$deleted}");

        return self::SUCCESS;
    }
}
