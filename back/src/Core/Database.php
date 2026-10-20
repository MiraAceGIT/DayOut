<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO. Lit la config depuis les variables d'environnement (.env).
 * Usage : Database::connection()->prepare(...)
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // On exige les variables critiques : pas de fallback silencieux
        // sur root sans mot de passe en prod.
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
        } catch (PDOException $e) {
            // Ne JAMAIS concatener $e->getMessage() : il peut exposer
            // host/user/password dans la trace client. Log serveur uniquement.
            error_log('[Database] connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed', 0, $e);
        }

        return self::$pdo;
    }
}
