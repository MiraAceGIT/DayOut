<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Event;

final class EventController extends Controller
{
    /** GET /api/events?limit=20&offset=0 */
    public function index(array $params): void
    {
        $limit  = Request::queryInt('limit',  20, 1, 100);
        $offset = Request::queryInt('offset',  0, 0, 10_000);

        $this->ok([
            'items'  => Event::listPublished($limit, $offset),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /** GET /api/events/{id} (id entier via regex {id:\d+}) */
    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $event = Event::findPublishedById($id);
        if ($event === null) {
            $this->error('NOT_FOUND', 'Event not found', 404, ['id' => $id]);
        }
        $this->ok($event);
    }
}
