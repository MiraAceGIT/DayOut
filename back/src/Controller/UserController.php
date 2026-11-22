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

    /** Inscription publique : sans session, le corps JSON obligatoire (415 sinon) ecarte les formulaires d'un autre site. */
    public function create(Request $request): Response
    {
        $dto = UserCreateDto::fromArray($request->json());

        return $this->ok($this->registration->register($dto), 201);
    }
}
