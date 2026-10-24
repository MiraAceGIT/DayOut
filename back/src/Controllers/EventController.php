<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Event;

final class EventController extends Controller
{
    private Event $events;
    private Category $categories;

    /** Le Router instancie sans argument ; les parametres servent aux tests. */
    public function __construct(?Event $events = null, ?Category $categories = null)
    {
        $this->events     = $events ?? new Event();
        $this->categories = $categories ?? new Category();
    }

    public function index(Request $request): Response
    {
        $limit  = $request->queryInt('limit',  20, 1, 100);
        $offset = $request->queryInt('offset',  0, 0, 10_000);

        return $this->ok([
            'items'  => $this->withCategories($this->events->listPublished($limit, $offset)),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    public function show(Request $request): Response
    {
        $id    = (int) $request->param('id');
        $event = $this->found($this->events->findPublishedById($id), 'Event', $id);
        return $this->ok($this->withCategories([$event])[0]);
    }

    /** 1 requete pour toute la page, quel que soit le nombre d'events. */
    private function withCategories(array $events): array
    {
        $byEvent = $this->categories->listActiveByEvent(array_column($events, 'id'));
        foreach ($events as &$event) {
            $event['categories'] = $byEvent[$event['id']] ?? [];
        }
        unset($event);
        return $events;
    }
}
