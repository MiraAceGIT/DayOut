<?php
declare(strict_types=1);

namespace App\Core\Orm;

use App\Core\Orm\Query\QueryBuilder;
use PDO;
use PDOStatement;
use Throwable;

/** Seule classe de l'ORM a manipuler PDOStatement : le reste ne voit que SQL + bindings. */
final class Connection
{
    private PDO $pdo;

    /** Transactions ouvertes par transaction() : 0 = aucune, 1 = la transaction, au-dela = savepoints. */
    private int $depth = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table, $this);
    }

    /** @return array<int, array<string, mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** false si aucune ligne. */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        return $this->run($sql, $bindings)->fetchColumn();
    }

    /** Id auto-increment, 0 si la table n'en a pas (cle composite). */
    public function insert(string $sql, array $bindings): int
    {
        $this->run($sql, $bindings);
        return (int) $this->pdo->lastInsertId();
    }

    public function affecting(string $sql, array $bindings): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * Imbricable : un appel interne pose un SAVEPOINT, son echec n'annule que son propre travail
     * (si l'appelant rattrape l'exception) ; seul l'appel le plus externe fait COMMIT.
     */
    public function transaction(callable $fn): mixed
    {
        $savepoint = $this->depth > 0 ? 'sp_' . $this->depth : null;
        $savepoint === null ? $this->pdo->beginTransaction() : $this->pdo->exec("SAVEPOINT $savepoint");
        $this->depth++;
        try {
            $result = $fn($this);
        } catch (Throwable $e) {
            $this->depth--;
            if ($savepoint !== null) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $this->depth--;
        $savepoint === null ? $this->pdo->commit() : $this->pdo->exec("RELEASE SAVEPOINT $savepoint");
        return $result;
    }

    private function run(string $sql, array $bindings): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        ValueBinder::bindAll($stmt, $bindings);
        $stmt->execute();
        return $stmt;
    }
}
