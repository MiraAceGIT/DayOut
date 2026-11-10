<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Log\Log;
use App\Model\Dao\Event\Event;
use App\Model\Dao\School\School;
use App\Model\Dto\Transport\TransportDto;
use App\Model\Dto\Transport\TransportOriginDto;
use App\Model\Exceptions\EventException;
use App\Model\Exceptions\TransportException;
use App\Model\Mappers\TransportMapper;
use App\Model\Transport\TransportProvider;
use App\Model\Transport\TransportProviderException;

/**
 * Compose l'info transport d'un evenement :
 * - origin : ecole de l'utilisateur connecte (depart d'itineraire pre-rempli)
 * - stops  : 3 arrets de TC les plus proches de l'evenement
 *
 * La source d'arrets (TransportProvider) est injectee : facile a mocker, facile a remplacer
 * (Overpass -> PRIM par ex.) sans toucher a la logique metier.
 */
final class TransportService
{
    public const DEFAULT_RADIUS_M = 800;
    public const DEFAULT_MAX_STOPS = 3;

    public function __construct(
        private readonly TransportProvider $provider,
        private readonly Event $events,
        private readonly School $schools,
        private readonly int $radiusM = self::DEFAULT_RADIUS_M,
        private readonly int $maxStops = self::DEFAULT_MAX_STOPS,
    ) {
    }

    public function forEvent(int $eventId, ?int $userId): TransportDto
    {
        $event = $this->events->findPublishedById($eventId) ?? throw EventException::notFound($eventId);
        if ($event['latitude'] === null || $event['longitude'] === null) {
            throw TransportException::eventHasNoCoordinates($eventId);
        }

        [$stops, $available] = $this->resolveStops((float) $event['latitude'], (float) $event['longitude']);

        return new TransportDto(
            origin: $this->resolveOrigin($userId),
            stops: TransportMapper::stopsToDtos($stops),
            stopsAvailable: $available,
        );
    }

    private function resolveOrigin(?int $userId): ?TransportOriginDto
    {
        if ($userId === null) {
            return null;
        }
        $school = $this->schools->findForUser($userId);
        if ($school === null || $school['latitude'] === null || $school['longitude'] === null) {
            return null;
        }
        return TransportMapper::originFromSchool($school);
    }

    /** @return array{0: list<\App\Model\Transport\TransportStop>, 1: bool} stops + available */
    private function resolveStops(float $latitude, float $longitude): array
    {
        try {
            $stops = $this->provider->findNearbyStops($latitude, $longitude, $this->radiusM);
            return [array_slice($stops, 0, $this->maxStops), true];
        } catch (TransportProviderException $e) {
            // API externe HS : la page doit rester utilisable (CdC D8). Journalise, degrade.
            Log::info('Transport provider failed, degrading response', ['reason' => $e->getMessage()]);
            return [[], false];
        }
    }
}
