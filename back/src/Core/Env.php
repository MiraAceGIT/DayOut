<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lecteur de fichier .env : `CLE=valeur`, lignes vides et `#` ignorees, commentaire en fin de ligne retire
 * sauf entre guillemets. Une variable deja definie dans l'environnement l'emporte (ex. DB_NAME=dayout_test
 * pour les tests).
 */
final class Env
{
    public static function load(string $file): void
    {
        foreach (self::parse(is_file($file) ? (string) file_get_contents($file) : '') as $key => $value) {
            if (getenv($key) !== false) {
                continue;
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    /** @return array<string, string> */
    public static function parse(string $content): array
    {
        $vars = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key   = trim($key);
            $value = trim($value);
            if (preg_match('/^(["\'])(.*)\1$/', $value, $m) === 1) {
                $value = $m[2]; // entre guillemets : pris tel quel, `#` compris
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value)); // commentaire en fin de ligne
            }
            $vars[$key] = $value;
        }
        return $vars;
    }
}
