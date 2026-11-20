<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Container;
use App\Core\Orm\Connection;
use App\Core\Router;
use Closure;
use LogicException;
use PDO;

final class AppRouter
{
    /** Routes de l'application ; pas via routes/api.php, dont le require_once ne recharge rien au 2e Router. */
    public static function load(?Container $container = null): Router
    {
        $router = new Router($container ?? self::container());
        foreach (glob(APP_ROOT . '/routes/*.php') ?: [] as $file) {
            if (basename($file) !== 'api.php') {
                require $file;
            }
        }
        return $router;
    }

    /** Conteneur de l'application (config/container.php) ; Connection remplacee si fournie. */
    public static function container(?Connection $connection = null): Container
    {
        /** @var Closure(Config): Container $containerFor */
        $containerFor = require APP_ROOT . '/config/container.php';
        $container = $containerFor(new Config());
        if ($connection !== null) {
            $container->set(Connection::class, static fn (): Connection => $connection);
        }
        return $container;
    }

    /** Connexion qui fait echouer le test au premier SQL : prouve qu'une requete est rejetee avant la base. */
    public static function noDatabase(): Connection
    {
        return new Connection(static fn (): PDO => throw new LogicException('The database must not be reached'));
    }
}
