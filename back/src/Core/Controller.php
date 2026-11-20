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

    /**
     * Identifiant entier strictement positif, sans zero en tete ni debordement ; sinon 404
     * (`/events/01` n'est pas une autre adresse de `/events/1`).
     */
    protected function idParam(Request $request, string $notFoundMessage, string $name = 'id'): int
    {
        $raw = $request->param($name);
        $id  = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
            throw HttpException::notFound($notFoundMessage);
        }
        return $id;
    }

    protected function userId(Request $request): int
    {
        $userId = $request->attribute(RequireAuth::USER_ID);
        return is_int($userId) ? $userId : throw HttpException::unauthorized();
    }
}
