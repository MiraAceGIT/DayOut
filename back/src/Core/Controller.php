<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Http\RequireAuth;

/**
 * Pour echouer : throw HttpException (HTTP) ou laisser remonter l'exception metier du service,
 * jamais de Response d'erreur construite a la main. Un 404 de ressource vient du service (EventException::notFound).
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

    /** Body JSON ; ValidationException (422) si invalide. */
    protected function validate(Request $request, array $rules): array
    {
        return Validator::check($request->json(), $rules);
    }

    /** Query string ; ValidationException (422) si invalide, plutot qu'une valeur par defaut silencieuse. */
    protected function validateQuery(Request $request, array $rules): array
    {
        return Validator::check($request->query(), $rules);
    }

    protected function userId(Request $request): int
    {
        $userId = $request->attribute(RequireAuth::USER_ID);
        return is_int($userId) ? $userId : throw HttpException::unauthorized();
    }
}
