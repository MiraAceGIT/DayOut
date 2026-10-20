<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Controller de base. Expose un acces rapide a la BDD et aux helpers
 * de reponse. Hereditage facultatif.
 */
abstract class Controller
{
    protected function db(): PDO
    {
        return Database::connection();
    }

    protected function ok(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function error(string $code, string $message, int $status, array $details = []): never
    {
        Response::error($code, $message, $status, $details);
    }

    protected function body(): array
    {
        return Request::json();
    }
}
