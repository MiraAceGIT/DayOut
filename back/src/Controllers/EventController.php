<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\EventRepository;

final class EventController extends Controller
{
    private EventRepository $events;

    /** Le Router instancie sans argument ; le parametre sert aux tests. */
    public function __construct(?EventRepository $events = null)
    {
        $this->events = $events ?? new EventRepository(Database::orm());
    }

    /** GET /api/events?limit=20&offset=0 */
    public function index(array $params): void
    {
        $limit  = Request::queryInt('limit',  20, 1, 100);
        $offset = Request::queryInt('offset',  0, 0, 10_000);

        $this->ok([
            'items'  => $this->events->listPublished($limit, $offset),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /** GET /api/events/{id} (id entier via regex {id:\d+}) */
    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $event = $this->events->findPublishedById($id);
        if ($event === null) {
            $this->error('NOT_FOUND', 'Event not found', 404, ['id' => $id]);
        }
        $this->ok($event);
    }
}
