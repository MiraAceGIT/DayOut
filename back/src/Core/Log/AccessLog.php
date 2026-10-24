<?php
declare(strict_types=1);

namespace App\Core\Log;

final class AccessLog
{
    /** Au shutdown, apres ErrorHandler : le statut inclut ainsi les 500 de secours (fatale, exception hors Kernel). */
    public static function register(): void
    {
        register_shutdown_function(static function (): void {
            $startedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

            Log::info('HTTP request', [
                'status'     => (int) http_response_code(),
                'durationMs' => round((microtime(true) - $startedAt) * 1000, 1),
            ]);
        });
    }
}
