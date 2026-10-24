<?php
declare(strict_types=1);

namespace App\Core\Log;

use Throwable;

/** Filets hors Kernel ; ne depend pas de la couche HTTP : la reponse de secours est ecrite a la main. */
final class ErrorHandler
{
    /** Types que set_error_handler() ne recoit jamais : visibles seulement au shutdown. */
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    private const INTERNAL_ERROR = '{"error":{"code":"INTERNAL_ERROR","message":"Something went wrong"}}';

    public static function register(): void
    {
        set_error_handler(self::handleError(...));
        set_exception_handler(self::handleException(...));
        register_shutdown_function(self::handleShutdown(...));
    }

    public static function handleError(int $type, string $message, string $file, int $line): bool
    {
        // Operateur @ ou niveau exclu par error_reporting() dans config.php.
        if ((error_reporting() & $type) === 0) {
            return false;
        }
        Log::log(self::levelFor($type), $message, ['file' => "$file:$line", 'type' => $type]);

        // display_errors actif (APP_DEBUG) : laisser aussi PHP afficher l'erreur.
        return !filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function handleException(Throwable $e): void
    {
        Log::critical('Uncaught exception', ['exception' => $e]);
        self::sendInternalError();
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || ($error['type'] & self::FATAL) === 0) {
            return;
        }
        Log::critical('Fatal error', ['message' => $error['message'], 'file' => $error['file'] . ':' . $error['line']]);
        self::sendInternalError();
    }

    private static function sendInternalError(): void
    {
        if (headers_sent()) {
            return;
        }
        // Jette une eventuelle sortie partielle (HTML d'erreur PHP, JSON tronque).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo self::INTERNAL_ERROR;
    }

    private static function levelFor(int $type): Level
    {
        return match (true) {
            ($type & (E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) !== 0 => Level::Warning,
            ($type & (E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED)) !== 0     => Level::Notice,
            default                                                                           => Level::Error,
        };
    }
}
