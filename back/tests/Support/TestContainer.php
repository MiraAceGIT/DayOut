<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Container;
use App\Core\Orm\Connection;
use Closure;
use LogicException;
use PDO;

/** Conteneur de l'application (config/container.php) pour les tests : services construits comme en prod. */
final class TestContainer
{
    /** @param Connection|PDO|null $db base des tests (SQLite, MariaDB de test) ; null = celle de la Config */
    public static function create(Connection|PDO|null $db = null): Container
    {
        /** @var Closure(Config): Container $containerFor */
        $containerFor = require APP_ROOT . '/config/container.php';
        $container = $containerFor(new Config());
        if ($db !== null) {
            $connection = $db instanceof PDO ? new Connection($db) : $db;
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
