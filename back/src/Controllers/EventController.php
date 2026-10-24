<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\Event;

final class EventController extends Controller
{
    private Event $events;

    /** Le Router instancie sans argument ; le parametre sert aux tests. */
    public function __construct(?Event $events = null)
    {
        $this->events = $events ?? new Event();
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

    /** GET /api/events/{id:\d+} */
    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $event = $this->events->findPublishedById($id);
        if ($event === null) {
            throw HttpException::notFound('Event not found', ['id' => $id]);
        }
        $this->ok($event);
    }
}
