<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Orm\Connection;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Facade statique configuree une fois au demarrage (configure()), connexion ouverte
 * a la premiere requete. Point d'acces unique : orm(), jamais le PDO brut.
 */
final class Database
{
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
        $config = self::$config ?? throw new RuntimeException('Database::configure() was not called');
        $dsn = "mysql:host={$config->dbHost};port={$config->dbPort};dbname={$config->dbName};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $config->dbUser, $config->dbPassword, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Les casts/bindings supposent une BDD en UTC : DEFAULT CURRENT_TIMESTAMP
            // doit l'etre aussi, quel que soit le fuseau du serveur.
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            // Ne JAMAIS concatener $e->getMessage() a l'exception : il peut
            // exposer host/user au client. Il reste dans `previous`, journalise
            // par l'appelant (ExceptionRenderer ou /health).
            throw new RuntimeException('Database connection failed', 0, $e);
        }
        return $pdo;
    }
}
