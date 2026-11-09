<?php
declare(strict_types=1);

namespace App\Model\Dao\Category;

use App\Core\Orm\Rows;
use App\Model\Dao\Model;

final class Category extends Model
{
    protected const TABLE = 'categories';

    protected const COLUMNS = ['id', 'slug', 'label', 'icon'];

    protected const CASTS = ['id' => 'int'];

    /**
     * Une seule requete pour toute une page d'events (anti N+1).
     *
     * @param array<int, int> $eventIds
     * @return array<int, array<int, array{slug: string, label: string}>> event_id => categories triees par label
     */
    public function listActiveByEvent(array $eventIds): array
    {
        $eventIds = array_values(array_unique($eventIds));
        if ($eventIds === []) {
            return [];
        }

        $rows = $this->table()
            ->select('ec.event_id', 'categories.slug', 'categories.label')
            ->join('event_categories AS ec', 'ec.category_id', '=', 'categories.id')
            ->whereIn('ec.event_id', $eventIds)
            ->where('categories.active', true)
            ->orderBy('categories.label')
            ->orderBy('categories.id')
            ->get();

        $byEvent = [];
        foreach (Rows::groupBy($rows, 'event_id') as $eventId => $categories) {
            $byEvent[(int) $eventId] = array_map(
                static fn (array $c): array => ['slug' => $c['slug'], 'label' => $c['label']],
                $categories
            );
        }
        return $byEvent;
    }
}
