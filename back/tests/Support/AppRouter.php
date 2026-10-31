<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Router;

final class AppRouter
{
    /** Routes de l'application ; pas via routes/api.php, dont le require_once ne recharge rien au 2e Router. */
    public static function load(): Router
    {
        $router = new Router();
        foreach (glob(APP_ROOT . '/routes/*.php') ?: [] as $file) {
            if (basename($file) !== 'api.php') {
                require $file;
            }
        }
        return $router;
    }
}
