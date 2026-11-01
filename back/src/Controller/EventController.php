<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\EventService;
use DateTimeImmutable;

final class EventController extends Controller
{
    private EventService $events;

    public function __construct(?EventService $events = null)
    {
        $this->events = $events ?? new EventService();
    }

    public function index(Request $request): Response
    {
        $query = $this->validateQuery($request, [
            'limit'  => 'int|min:1|max:100',
            'offset' => 'int|min:0|max:10000',
            'from'   => 'datetime',
        ]);
        $limit  = $query['limit'] ?? 20;
        $offset = $query['offset'] ?? 0;
        $from   = new DateTimeImmutable($query['from'] ?? 'now');

        return $this->ok([
            'items'  => $this->events->listUpcoming($from, $limit, $offset),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    public function show(Request $request): Response
    {
        return $this->ok($this->events->getPublished((int) $request->param('id')));
    }
}
