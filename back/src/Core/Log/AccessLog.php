<?php
declare(strict_types=1);

namespace App\Core\Log;

/** Une ligne info.log par requete HTTP : methode, chemin, statut, duree. */
final class AccessLog
{
    /** Au shutdown car Response::send() termine par exit : le dispatch ne rend jamais la main. */
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
