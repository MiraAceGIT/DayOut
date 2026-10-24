<?php
declare(strict_types=1);

namespace App\Core\Log;

use App\Core\HttpException;
use App\Core\Response;
use Throwable;

/**
 * Gestionnaire global : aucune exception n'a besoin d'etre attrapee dans les controllers.
 *   HttpException -> son statut et son message ; toute autre exception -> log + 500 generique.
 * Capte aussi les warnings PHP et les erreurs fatales.
 */
final class ErrorHandler
{
    /** Types que set_error_handler() ne recoit jamais : visibles seulement au shutdown. */
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

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

        // En debug, laisser aussi PHP afficher l'erreur.
        return getenv('APP_DEBUG') !== 'true';
    }

    public static function handleException(Throwable $e): never
    {
        if ($e instanceof HttpException) {
            Response::error($e->errorCode, $e->getMessage(), $e->status, $e->details);
        }
        // Jamais exposer la stack ni le message au client, meme en debug.
        Log::error('Unhandled exception', ['exception' => $e]);
        Response::error('INTERNAL_ERROR', 'Something went wrong', 500);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || ($error['type'] & self::FATAL) === 0) {
            return;
        }
        Log::critical('Fatal error', ['message' => $error['message'], 'file' => $error['file'] . ':' . $error['line']]);
        if (!headers_sent()) {
            http_response_code(500);
        }
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
