<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Orm\Connection;
use PDO;
use PDOException;
use RuntimeException;

/** Ouverture de la BDD ; les models recoivent la Connection du Container, jamais le PDO brut. */
final class Database
{
    private const CONNECT_TIMEOUT = 3;

    /** Connexion ouverte au premier SQL : une requete rejetee avant (404, 422) ne touche pas la base. */
    public static function connection(Config $config): Connection
    {
        return new Connection(static fn (): PDO => self::open($config));
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
