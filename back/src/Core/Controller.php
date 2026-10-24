<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base des controllers : une action par route, signature `action(Request $request): Response`.
 * Pour echouer : throw HttpException (jamais de Response d'erreur construite a la main).
 */
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

    /** Valide le body JSON : voir Validator pour les regles. */
    protected function validate(Request $request, array $rules): array
    {
        return Validator::check($request->json(), $rules);
    }

    /** Ligne trouvee, ou 404 "<Resource> not found" avec l'id en details. */
    protected function found(?array $row, string $resource, int|string $id): array
    {
        return $row ?? throw HttpException::notFound("$resource not found", ['id' => $id]);
    }
}
