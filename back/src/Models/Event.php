<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model Event : encapsule les requetes SQL sur la table `events`.
 * Pas de logique metier ici, uniquement l'acces aux donnees + le casting
 * necessaire pour exposer un contrat JSON stable.
 */
final class Event
{
    /** Colonnes exposees par l'API (ne jamais utiliser SELECT *). */
    private const PUBLIC_COLUMNS = 'id, creator_id, title, slug, description, cover_image_url,
        start_at, end_at, venue_name, address_line, city, postal_code,
        capacity, registered_count, price_cents, currency, is_paid,
        external_ticket_url, school_restricted, restricted_school_id,
        status, published_at, created_at';

    /** Liste des events publies, pagination par limit/offset. */
    public static function listPublished(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT " . self::PUBLIC_COLUMNS . "
                FROM events
                WHERE status = 'published'
                ORDER BY start_at ASC
                LIMIT :lim OFFSET :off";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([self::class, 'cast'], $stmt->fetchAll());
    }

    /** Un event par id, uniquement s'il est publie. */
    public static function findPublishedById(int $id): ?array
    {
        $sql = "SELECT " . self::PUBLIC_COLUMNS . "
                FROM events
                WHERE id = :id AND status = 'published'
                LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ? self::cast($row) : null;
    }

    /**
     * Casts coherents pour le contrat JSON :
     *  - booleens -> bool (plutot que "0"/"1" string renvoyes par PDO MySQL)
     *  - entiers -> int (idem)
     *  - dates   -> ISO 8601 UTC
     */
    private static function cast(array $row): array
    {
        $row['is_paid']           = (bool) $row['is_paid'];
        $row['school_restricted'] = (bool) $row['school_restricted'];
        $row['price_cents']       = (int) $row['price_cents'];
        $row['registered_count']  = (int) $row['registered_count'];
        $row['capacity']          = $row['capacity'] !== null ? (int) $row['capacity'] : null;
        $row['start_at']          = self::toIso($row['start_at']);
        $row['end_at']            = self::toIso($row['end_at']);
        $row['published_at']      = self::toIso($row['published_at']);
        $row['created_at']        = self::toIso($row['created_at']);
        return $row;
    }

    private static function toIso(?string $dbDate): ?string
    {
        if ($dbDate === null) {
            return null;
        }
        // La BDD stocke en UTC (SET time_zone='+00:00' dans le schema).
        return (new \DateTimeImmutable($dbDate, new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
    }
}
