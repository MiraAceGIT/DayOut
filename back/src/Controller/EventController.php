<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Mappers\EventMapper;
use App\Model\Services\EventService;

final class EventController extends Controller
{
    private EventService $events;

    public function __construct(?EventService $events = null)
    {
        $this->events = $events ?? new EventService();
    }

    public function index(Request $request): Response
    {
        $limit  = $request->queryInt('limit',  20, 1, 100);
        $offset = $request->queryInt('offset',  0, 0, 10_000);

        return $this->ok([
            'items'  => EventMapper::toDtos($this->events->listPublished($limit, $offset)),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    public function show(Request $request): Response
    {
        return $this->ok(EventMapper::toDto($this->events->getPublished((int) $request->param('id'))));
    }
}
