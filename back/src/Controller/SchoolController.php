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
        return $this->ok($this->schools->findActive());
    }
}
