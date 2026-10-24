<?php
declare(strict_types=1);

namespace App\Core;

/** Pour echouer : throw HttpException, jamais de Response d'erreur construite a la main. */
abstract class Controller
{
    protected function ok(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function noContent(): Response
    {
        return Response::noContent();
    }

    protected function validate(Request $request, array $rules): array
    {
        return Validator::check($request->json(), $rules);
    }

    protected function found(?array $row, string $resource, int|string $id): array
    {
        return $row ?? throw HttpException::notFound("$resource not found", ['id' => $id]);
    }
}
