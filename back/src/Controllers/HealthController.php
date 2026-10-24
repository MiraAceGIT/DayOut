<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Log\Log;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class HealthController extends Controller
{
    public function index(Request $request): Response
    {
        $db = 'down';
        try {
            Database::orm()->scalar('SELECT 1');
            $db = 'up';
        } catch (Throwable $e) {
            // /health doit toujours repondre, meme BDD down.
            Log::error('Health check: database unreachable', ['exception' => $e]);
        }

        return $this->ok([
            'api'  => 'up',
            'db'   => $db,
            'time' => gmdate(DATE_ATOM),
        ]);
    }
}
