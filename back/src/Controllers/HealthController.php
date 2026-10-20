<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use Throwable;

final class HealthController extends Controller
{
    /** GET /api/health : smoke test (API + BDD joignables). */
    public function index(array $params): void
    {
        $db = 'down';
        try {
            $this->db()->query('SELECT 1');
            $db = 'up';
        } catch (Throwable $e) {
            // Log serveur mais on continue : /health doit toujours repondre.
            error_log('[Health] DB check failed: ' . $e->getMessage());
        }

        $this->ok([
            'api'  => 'up',
            'db'   => $db,
            'time' => date(DATE_ATOM),
        ]);
    }
}
