<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Database;
use LogicException;
use PDO;

/** Connexion a la base de test (TEST_DB_NAME), jamais celle de dev. */
final class TestDatabase
{
    private static ?Config $config = null;

    public static function pdo(): PDO
    {
        $testDb = (string) getenv('TEST_DB_NAME');
        if ($testDb === '') {
            throw new LogicException('TEST_DB_NAME is not set (run tests/run.sh --integration)');
        }
        if (self::$config === null) {
            putenv("DB_NAME=$testDb");
            self::$config = require APP_ROOT . '/config/config.php';
        }
        return Database::open(self::$config ?? throw new LogicException('config.php did not return a Config'));
    }
}
