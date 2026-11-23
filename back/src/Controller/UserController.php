<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Services\RegistrationService;

final class UserController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    /**
     * Inscription publique, sans session donc sans jeton CSRF a verifier. Un formulaire HTML d'un autre site ne peut
     * envoyer que du texte ou du multipart : il recoit 415 (corps JSON obligatoire). Un `fetch` JSON depuis un autre
     * site declenche une verification CORS prealable, refusee hors des origines de CORS_ORIGIN.
     * 201 sans en-tete Location : aucune route GET /users/{id} n'existe (profil = SCRUM-43).
     */
    public function create(Request $request): Response
    {
        $dto = UserCreateDto::fromArray($request->json());

        return $this->ok($this->registration->register($dto), 201);
    }
}
