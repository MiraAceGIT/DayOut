<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;

final class Request
{
    /** Body vide -> [] ; JSON invalide -> HttpException 400. */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw HttpException::badRequest('Invalid JSON body: ' . $e->getMessage(), [], $e);
        }
        if (!is_array($data)) {
            throw HttpException::badRequest('JSON body must be an object or array');
        }
        return $data;
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }

    /** Hors [min, max] ou non entier -> $default. */
    public static function queryInt(string $key, int $default, int $min, int $max): int
    {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default, 'min_range' => $min, 'max_range' => $max],
        ]);
        return (int) $value;
    }
}
