<?php
declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * PDO de test qui joue une requete concurrente : `beforeNextTransaction` s'execute (hors transaction, donc valide
 * aussitot) juste avant la prochaine transaction, comme si une autre requete HTTP s'etait glissee entre les
 * verifications du service et ses ecritures.
 */
final class RacingPdo extends PDO
{
    public ?string $beforeNextTransaction = null;

    public function beginTransaction(): bool
    {
        if ($this->beforeNextTransaction !== null) {
            $sql = $this->beforeNextTransaction;
            $this->beforeNextTransaction = null;
            $this->exec($sql);
        }
        return parent::beginTransaction();
    }
}
