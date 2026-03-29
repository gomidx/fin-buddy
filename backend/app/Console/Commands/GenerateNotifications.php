<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateNotifications extends Command
{
    protected $signature   = 'notifications:generate';
    protected $description = 'Gera notificações automáticas: recorrências próximas, metas e inatividade';

    public function handle(NotificationService $service): int
    {
        $this->info('[notifications:generate] Iniciando geração de notificações...');

        try {
            $results = $service->generateForAllUsers();

            $this->info("  ✔ Recorrências: {$results['recurring']}");
            $this->info("  ✔ Metas:        {$results['goals']}");
            $this->info("  ✔ Inatividade:  {$results['no_activity']}");

            Log::info('Notifications generated', $results);
        } catch (\Throwable $e) {
            $this->error("[notifications:generate] Erro: {$e->getMessage()}");
            Log::error('Error generating notifications', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
