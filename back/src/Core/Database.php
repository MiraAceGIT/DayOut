<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Orm\Connection;
use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;
    private static ?Connection $orm = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // Pas de fallback silencieux sur root sans mot de passe en prod.
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $required) {
            if (getenv($required) === false) {
                throw new RuntimeException("Missing environment variable: $required");
            }
        }

        $host = (string) getenv('DB_HOST');
        $port = (string) getenv('DB_PORT');
        $name = (string) getenv('DB_NAME');
        $user = (string) getenv('DB_USER');
        $pass = (string) getenv('DB_PASSWORD'); // peut etre vide en XAMPP local

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Les casts/bindings supposent une BDD en UTC : DEFAULT CURRENT_TIMESTAMP
            // doit l'etre aussi, quel que soit le fuseau du serveur.
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            // Ne JAMAIS concatener $e->getMessage() a l'exception : il peut
            // exposer host/user/password au client. Log serveur uniquement.
            error_log('[Database] connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed', 0, $e);
        }

        return self::$pdo;
    }

    /** Instance unique par requete : une transaction ouverte couvre tous les models. */
    public static function orm(): Connection
    {
        return self::$orm ??= new Connection(self::connection());
    }
}
