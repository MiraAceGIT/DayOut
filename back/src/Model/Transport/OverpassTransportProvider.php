<?php
declare(strict_types=1);

namespace App\Model\Transport;

use App\Core\Log\Log;
use Closure;
use Throwable;

/**
 * Source d'arrets : OpenStreetMap via l'API Overpass (overpass-api.de).
 *
 * - HTTP : cURL, injectable (ferme a l'extension pour tests sans reseau).
 * - Cache : fichier JSON par cle (lat, lng, rayon arrondis), TTL en secondes.
 * - Erreur : TransportProviderException ; le service la rattrape et renvoie une reponse neutre.
 */
final class OverpassTransportProvider implements TransportProvider
{
    private const DEFAULT_ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /** Arretes "conteneurs" Overpass : on enleve les stop_position (quais, points techniques souvent dupliques). */
    private const QUERY_TEMPLATE = <<<'OVERPASS'
        [out:json][timeout:%1$d];
        (
          node["highway"="bus_stop"](around:%2$d,%3$F,%4$F);
          node["railway"="station"](around:%2$d,%3$F,%4$F);
          node["railway"="tram_stop"](around:%2$d,%3$F,%4$F);
          node["station"="subway"](around:%2$d,%3$F,%4$F);
          node["public_transport"="station"](around:%2$d,%3$F,%4$F);
        );
        out body;
        OVERPASS;

    /** @var Closure(string, string): string */
    private readonly Closure $httpPost;

    public function __construct(
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
        private readonly string $cacheDir = '',
        private readonly int $cacheTtlSec = 86_400,
        private readonly int $timeoutSec = 15,
        ?callable $httpPost = null,
    ) {
        $this->httpPost = $httpPost !== null ? Closure::fromCallable($httpPost) : self::curlPost(...);
    }

    public function findNearbyStops(float $latitude, float $longitude, int $radiusM): array
    {
        $cacheKey = $this->cacheKey($latitude, $longitude, $radiusM);
        $cached = $this->cacheRead($cacheKey);
        if ($cached !== null) {
            return $this->sortByDistance($cached, $latitude, $longitude);
        }

        $query = sprintf(self::QUERY_TEMPLATE, $this->timeoutSec, $radiusM, $latitude, $longitude);

        try {
            $rawJson = ($this->httpPost)($this->endpoint, 'data=' . urlencode($query));
        } catch (Throwable $e) {
            throw new TransportProviderException('Overpass request failed: ' . $e->getMessage(), 0, $e);
        }

        $stops = $this->parse($rawJson, $latitude, $longitude);
        $this->cacheWrite($cacheKey, $stops);

        return $this->sortByDistance($stops, $latitude, $longitude);
    }

    /**
     * @return list<TransportStop>
     * @throws TransportProviderException
     */
    private function parse(string $rawJson, float $centerLat, float $centerLng): array
    {
        $payload = json_decode($rawJson, true);
        if (!is_array($payload) || !isset($payload['elements']) || !is_array($payload['elements'])) {
            throw new TransportProviderException('Overpass payload is not a valid JSON object with "elements"');
        }

        $stops = [];
        $seen  = []; // deduplication name+type (Overpass renvoie souvent plusieurs noeuds par station)
        foreach ($payload['elements'] as $element) {
            if (!is_array($element) || !isset($element['lat'], $element['lon']) || !isset($element['tags']) || !is_array($element['tags'])) {
                continue;
            }
            $tags = $element['tags'];
            $name = isset($tags['name']) && is_string($tags['name']) ? trim($tags['name']) : '';
            if ($name === '') {
                continue;
            }

            $type = self::classify($tags);
            $key  = $type . "\0" . mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $distance = (int) round(Geo::haversineDistanceM($centerLat, $centerLng, (float) $element['lat'], (float) $element['lon']));

            $stops[] = new TransportStop(
                name: $name,
                type: $type,
                latitude: (float) $element['lat'],
                longitude: (float) $element['lon'],
                distanceM: $distance,
                lines: self::extractLines($tags),
            );
        }

        return $stops;
    }

    /** @param array<string, mixed> $tags */
    private static function classify(array $tags): string
    {
        return match (true) {
            ($tags['station']           ?? null) === 'subway',
            ($tags['subway']            ?? null) === 'yes' => 'subway',

            ($tags['railway']           ?? null) === 'tram_stop',
            ($tags['tram']              ?? null) === 'yes',
            ($tags['station']           ?? null) === 'tram' => 'tram',

            ($tags['station']           ?? null) === 'light_rail',
            ($tags['light_rail']        ?? null) === 'yes',
            ($tags['train']             ?? null) === 'yes',
            ($tags['railway']           ?? null) === 'station' => 'rail',

            ($tags['highway']           ?? null) === 'bus_stop',
            ($tags['bus']               ?? null) === 'yes' => 'bus',

            default => 'other',
        };
    }

    /**
     * @param array<string, mixed> $tags
     * @return list<string>
     */
    private static function extractLines(array $tags): array
    {
        $ref = $tags['route_ref'] ?? $tags['ref'] ?? null;
        if (!is_string($ref) || trim($ref) === '') {
            return [];
        }
        $lines = preg_split('/\s*[;,]\s*/', trim($ref)) ?: [];
        return array_values(array_filter(array_map('trim', $lines), static fn (string $s): bool => $s !== ''));
    }

    /**
     * @param list<TransportStop> $stops
     * @return list<TransportStop>
     */
    private function sortByDistance(array $stops, float $centerLat, float $centerLng): array
    {
        // Peut etre lu depuis le cache avec un centre different : on recalcule la distance pour etre exact.
        $stops = array_map(
            static fn (TransportStop $s): TransportStop => $s->withDistance(
                (int) round(Geo::haversineDistanceM($centerLat, $centerLng, $s->latitude, $s->longitude))
            ),
            $stops
        );
        usort($stops, static fn (TransportStop $a, TransportStop $b): int => $a->distanceM <=> $b->distanceM);
        return $stops;
    }

    private function cacheKey(float $lat, float $lng, int $radiusM): string
    {
        // ~110m de precision sur lat, moins sur lng : partage les arrets pour des events voisins.
        return sprintf('%d_%d_r%d', (int) round($lat * 1000), (int) round($lng * 1000), $radiusM);
    }

    /** @return list<TransportStop>|null */
    private function cacheRead(string $key): ?array
    {
        $path = $this->cachePath($key);
        if ($path === null || !is_file($path)) {
            return null;
        }
        if ((time() - (int) filemtime($path)) > $this->cacheTtlSec) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }

        $stops = [];
        foreach ($decoded as $row) {
            if (!is_array($row)
                || !isset($row['name'], $row['type'], $row['latitude'], $row['longitude'])
                || !in_array($row['type'], TransportStop::TYPES, true)
            ) {
                return null;
            }
            $lines = $row['lines'] ?? [];
            $stops[] = new TransportStop(
                name: (string) $row['name'],
                type: (string) $row['type'],
                latitude: (float) $row['latitude'],
                longitude: (float) $row['longitude'],
                distanceM: 0, // recalcule par sortByDistance
                lines: is_array($lines) ? array_values(array_map(strval(...), $lines)) : [],
            );
        }
        return $stops;
    }

    /** @param list<TransportStop> $stops */
    private function cacheWrite(string $key, array $stops): void
    {
        $path = $this->cachePath($key);
        if ($path === null) {
            return;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            Log::warning('Transport cache dir not writable', ['dir' => $dir]);
            return;
        }

        $payload = array_map(static fn (TransportStop $s): array => [
            'name'      => $s->name,
            'type'      => $s->type,
            'latitude'  => $s->latitude,
            'longitude' => $s->longitude,
            'lines'     => $s->lines,
        ], $stops);

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false
            || !@rename($tmp, $path)) {
            @unlink($tmp);
            Log::warning('Transport cache write failed', ['path' => $path]);
        }
    }

    private function cachePath(string $key): ?string
    {
        if ($this->cacheDir === '') {
            return null;
        }
        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . $key . '.json';
    }

    /** @throws TransportProviderException */
    private function curlPost(string $url, string $body): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new TransportProviderException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $this->timeoutSec,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSec),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_USERAGENT      => 'DayOut/1.0 (+https://dayout.local)',
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || !is_string($response)) {
            throw new TransportProviderException("Overpass HTTP error: $error");
        }
        if ($status < 200 || $status >= 300) {
            throw new TransportProviderException("Overpass HTTP $status");
        }
        return $response;
    }
}
