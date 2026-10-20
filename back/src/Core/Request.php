<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;
use RuntimeException;

/**
 * Helpers de lecture de la requete HTTP (body JSON, query string).
 * Toutes les erreurs de parsing JSON remontent via RuntimeException,
 * attrapee par le dispatcher pour renvoyer un 400 explicite.
 */
final class Request
{
    /** Retourne le body JSON decode (tableau associatif) ou []. */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON body: ' . $e->getMessage(), 400, $e);
        }
        if (!is_array($data)) {
            throw new RuntimeException('JSON body must be an object or array', 400);
        }
        return $data;
    }

    /** Lit un parametre de query string ($_GET) avec valeur par defaut. */
    public static function query(string $key, ?string $default = null): ?string
    {
        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }

    /** Entier valide dans [min, max], valeur par defaut sinon. */
    public static function queryInt(string $key, int $default, int $min, int $max): int
    {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default, 'min_range' => $min, 'max_range' => $max],
        ]);
        return (int) $value;
    }
}
