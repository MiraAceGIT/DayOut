<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Orm\Connection;
use PDO;
use PDOException;
use RuntimeException;

/** Point d'acces unique a la BDD : orm(), jamais le PDO brut. */
final class Database
{
    private const CONNECT_TIMEOUT = 3;

    private static ?Config $config = null;
    private static ?Connection $orm = null;

    public static function configure(Config $config): void
    {
        self::$config = $config;
        self::$orm    = null;
    }

    /** Instance unique par requete : une transaction ouverte couvre tous les models. */
    public static function orm(): Connection
    {
        return self::$orm ??= new Connection(self::connect());
    }

    private static function connect(): PDO
    {
        return self::open(self::$config ?? throw new RuntimeException('Database::configure() was not called'));
    }

    public static function open(Config $config): PDO
    {
        $dsn = "mysql:host={$config->dbHost};port={$config->dbPort};dbname={$config->dbName};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $config->dbUser, $config->dbPassword, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Delai de connexion (s) : BDD injoignable -> echec rapide, /health repond `db: down` sans bloquer.
                PDO::ATTR_TIMEOUT            => self::CONNECT_TIMEOUT,
            ]);
            // Casts et bindings supposent une session UTC (DEFAULT CURRENT_TIMESTAMP compris).
            $pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO'");
        } catch (PDOException $e) {
            // Jamais $e->getMessage() ici : il peut exposer host/user. Il reste journalise via `previous`.
            throw new RuntimeException('Database connection failed', 0, $e);
        }
        return $pdo;
    }
}
