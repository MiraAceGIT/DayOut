<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Query\EventFindQuery;
use App\Model\Services\EventService;

final class EventController extends Controller
{
    public function __construct(private readonly EventService $events)
    {
    }

    public function findMany(Request $request): Response
    {
        $query = EventFindQuery::fromQuery($request->query());

        return $this->ok($this->events->findMany($query->toParams()));
    }

    public function findOne(Request $request): Response
    {
        $id = $this->eventId($request);

        return $this->ok($this->events->findOne($id));
    }

    public function create(Request $request): Response
    {
        $userId = $this->userId($request);
        $dto = EventCreateDto::fromArray($request->json());

        return $this->ok($this->events->create($dto, $userId), 201);
    }

    public function update(Request $request): Response
    {
        $userId = $this->userId($request);
        $id = $this->eventId($request);
        $dto = EventUpdateDto::fromArray($request->json());

        return $this->ok($this->events->update($id, $dto, $userId));
    }

    public function delete(Request $request): Response
    {
        $userId = $this->userId($request);
        $this->events->delete($this->eventId($request), $userId);

        return $this->noContent();
    }

    private function eventId(Request $request): int
    {
        return $this->idParam($request, 'Event not found');
    }
}
