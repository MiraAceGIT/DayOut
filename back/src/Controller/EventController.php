<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Query\EventFindQuery;
use App\Model\Services\EventService;

final class EventController extends Controller
{
    private ?EventService $events;

    public function __construct(?EventService $events = null)
    {
        $this->events = $events;
    }

    public function findMany(Request $request): Response
    {
        $query = EventFindQuery::fromQuery($request->query());

        return $this->ok($this->events()->findMany($query->toParams()));
    }

    public function findOne(Request $request): Response
    {
        $id = self::eventId($request);

        return $this->ok($this->events()->findOne($id));
    }

    public function create(Request $request): Response
    {
        $userId = $this->userId($request);
        $dto = EventCreateDto::fromArray($request->json());

        return $this->ok($this->events()->create($dto, $userId), 201);
    }

    public function update(Request $request): Response
    {
        $userId = $this->userId($request);
        $id = self::eventId($request);
        $dto = EventUpdateDto::fromArray($request->json());

        return $this->ok($this->events()->update($id, $dto, $userId));
    }

    public function delete(Request $request): Response
    {
        $userId = $this->userId($request);
        $this->events()->delete(self::eventId($request), $userId);

        return $this->noContent();
    }

    private static function eventId(Request $request): int
    {
        $rawId = $request->param('id');
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || preg_match('/^[1-9][0-9]*$/D', $rawId) !== 1) {
            throw HttpException::notFound('Event not found');
        }
        return $id;
    }

    private function events(): EventService
    {
        return $this->events ??= new EventService();
    }
}
