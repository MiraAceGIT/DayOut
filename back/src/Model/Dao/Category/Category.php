<?php
declare(strict_types=1);

namespace App\Model\Dao\Category;

use App\Core\Orm\Rows;
use App\Model\Dao\Dao;

final class Category extends Dao
{
    protected const TABLE = 'categories';

    protected const COLUMNS = ['id', 'slug', 'label', 'icon'];

    protected const CASTS = ['id' => 'int'];

    /** @return list<array{slug: string, label: string}> categories actives, par label */
    public function listActive(): array
    {
        $rows = $this->query()->where('active', true)->orderBy('label')->orderBy('id')->get();
        return array_map(static fn (array $c): array => ['slug' => (string) $c['slug'], 'label' => (string) $c['label']], array_values($rows));
    }

    /**
     * @param list<string> $slugs
     * @return array<string, int> slug => id, pour les seules categories actives (un slug inconnu est absent)
     */
    public function idsByActiveSlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }
        $rows = $this->query()->whereIn('slug', array_values(array_unique($slugs)))->where('active', true)->get();
        return array_column($rows, 'id', 'slug');
    }

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
