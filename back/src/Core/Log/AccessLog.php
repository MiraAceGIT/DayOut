<?php
declare(strict_types=1);

namespace App\Core\Log;

final class AccessLog
{
    /**
     * Au shutdown, apres ErrorHandler : le statut inclut ainsi les 500 de secours (fatale, exception hors Kernel).
     *
     * @param float $startedAt debut de la requete ($_SERVER['REQUEST_TIME_FLOAT'], passe par public/index.php)
     */
    public static function register(float $startedAt): void
    {
        register_shutdown_function(static function () use ($startedAt): void {
            Log::info('HTTP request', [
                'status'     => (int) http_response_code(),
                'durationMs' => round((microtime(true) - $startedAt) * 1000, 1),
            ]);
        });
    }
}
