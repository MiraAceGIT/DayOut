<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
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
    public function index(Request $request): Response
    {
        $limit  = $request->queryInt('limit',  20, 1, 100);
        $offset = $request->queryInt('offset',  0, 0, 10_000);

        return $this->ok([
            'items'  => $this->events->listPublished($limit, $offset),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /** GET /api/events/{id:\d+} */
    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        return $this->ok($this->found($this->events->findPublishedById($id), 'Event', $id));
    }
}
