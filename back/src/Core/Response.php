<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;

/**
 * Reponses JSON standardisees. Toutes les reponses de l'API passent par ici
 * pour garantir un format coherent.
 *
 * Succes :
 *   { "data": ... }
 *
 * Erreur :
 *   { "error": { "code": "NOT_FOUND", "message": "...", "details": {...} } }
 */
final class Response
{
    /** Reponse de succes. */
    public static function json(mixed $data, int $status = 200): never
    {
        self::send(['data' => $data], $status);
    }

    /** Reponse d'erreur normalisee. */
    public static function error(string $code, string $message, int $status, array $details = []): never
    {
        $payload = ['error' => ['code' => $code, 'message' => $message]];
        if ($details !== []) {
            $payload['error']['details'] = $details;
        }
        self::send($payload, $status);
    }

    /** Pas de contenu (204). */
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
            // Fallback minimal : si l'encodage echoue (UTF-8 invalide, ressource),
            // on force une erreur serveur plutot que de laisser un body vide avec 200.
            http_response_code(500);
            echo '{"error":{"code":"ENCODING_ERROR","message":"Response could not be encoded"}}';
            error_log('[Response] json_encode failed: ' . $e->getMessage());
        }
        exit;
    }
}
