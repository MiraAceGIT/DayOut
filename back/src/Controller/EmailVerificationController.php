<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\RegistrationService;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    /** POST et non GET : un lien ouvert par un antivirus ou un apercu de messagerie ne confirme rien. */
    public function create(Request $request): Response
    {
        $token = $this->validate($request, ['token' => 'required|string|max:128'])['token'];

        return $this->ok($this->registration->confirmEmail($token));
    }
}
