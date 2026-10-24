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

    /** UPDATE / DELETE : nombre de lignes touchees. */
    public function affecting(string $sql, array $bindings): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * $fn recoit la Connection ; sa valeur de retour est renvoyee apres commit.
     * Pas d'imbrication : appeler transaction() dans $fn leve une PDOException.
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function run(string $sql, array $bindings): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        ValueBinder::bindAll($stmt, $bindings);
        $stmt->execute();
        return $stmt;
    }
}
