<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\EventService;
use DateTimeImmutable;

final class EventController extends Controller
{
    private ?EventService $events;

    public function __construct(?EventService $events = null)
    {
        $this->events = $events;
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

        return $this->ok($this->events()->listUpcomingPage($from, $limit, $offset));
    }

    public function show(Request $request): Response
    {
        $rawId = $request->param('id');
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        // Valider avant toute connexion a la base et avant le cast (pas de troncature d'un id trop grand).
        if ($id === false || preg_match('/^[1-9][0-9]*$/D', $rawId) !== 1) {
            throw HttpException::notFound('Event not found');
        }
        return $this->ok($this->events()->getPublishedDetail($id));
    }

    private function events(): EventService
    {
        return $this->events ??= new EventService();
    }
}
