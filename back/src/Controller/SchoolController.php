<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\SchoolService;

final class SchoolController extends Controller
{
    public function __construct(private readonly SchoolService $schools)
    {
    }

    public function findMany(Request $request): Response
    {
        // Referentiel stable : cache partage 1 h (une modification du back-office, SCRUM-78, se voit au plus 1 h apres).
        return $this->ok($this->schools->findActive())->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
