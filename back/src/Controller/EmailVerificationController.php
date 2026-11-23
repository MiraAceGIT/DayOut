<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\EmailConfirmationService;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailConfirmationService $confirmations)
    {
    }

    /**
     * Consomme le jeton du lien (action, pas creation : 200 et non 201). POST et non GET : un antivirus ou un apercu
     * de messagerie qui ouvre le lien ne confirme rien. Jeton dans le corps et non dans l'URL : il n'apparait ni
     * dans les logs d'acces ni dans l'historique.
     */
    public function create(Request $request): Response
    {
        $token = $this->validate($request, ['token' => 'required|string|max:128'])['token'];

        return $this->ok($this->confirmations->confirm($token));
    }
}
