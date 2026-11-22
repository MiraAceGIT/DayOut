<?php
declare(strict_types=1);

namespace App\Model\Dao\EmailVerification;

use App\Model\Dao\Dao;
use DateTimeImmutable;

/** Jetons de confirmation d'adresse : seul le hash SHA-256 est stocke, jamais le jeton envoye. */
final class EmailVerification extends Dao
{
    protected const TABLE = 'email_verifications';

    public function createFor(int $userId, string $tokenHash, DateTimeImmutable $expiresAt): int
    {
        return $this->table()->insert(['user_id' => $userId, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt]);
    }

    /**
     * Jeton et statut createur de son compte, en une requete.
     *
     * @return array{id: int, user_id: int, expires_at: string, used_at: ?string, creator_status: string}|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $row = $this->table()
            ->select('email_verifications.id', 'email_verifications.user_id', 'email_verifications.expires_at',
                'email_verifications.used_at', 'u.creator_status')
            ->join('users AS u', 'u.id', '=', 'email_verifications.user_id')
            ->where('email_verifications.token_hash', $tokenHash)
            ->first();
        if ($row === null) {
            return null;
        }
        return [
            'id'             => (int) $row['id'],
            'user_id'        => (int) $row['user_id'],
            'expires_at'     => (string) $row['expires_at'],
            'used_at'        => $row['used_at'] === null ? null : (string) $row['used_at'],
            'creator_status' => (string) $row['creator_status'],
        ];
    }

    /** Atomique : 0 si le jeton a deja servi entre-temps (deux clics simultanes). */
    public function markUsed(int $id, DateTimeImmutable $at): int
    {
        return $this->table()->where('id', $id)->whereNull('used_at')->update(['used_at' => $at]);
    }
}
