<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

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
