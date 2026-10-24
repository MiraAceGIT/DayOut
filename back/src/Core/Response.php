<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;

/**
 * Format unique des reponses : { "data": ... } ou
 * { "error": { "code", "message", "details"? } }.
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        self::send(['data' => $data], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): never
    {
        $payload = ['error' => ['code' => $code, 'message' => $message]];
        if ($details !== []) {
            $payload['error']['details'] = $details;
        }
        self::send($payload, $status);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        try {
            echo json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            // Sinon le client recevrait un body vide avec le statut d'origine (ex. 200).
            http_response_code(500);
            echo '{"error":{"code":"ENCODING_ERROR","message":"Response could not be encoded"}}';
            error_log('[Response] json_encode failed: ' . $e->getMessage());
        }
        exit;
    }
}
