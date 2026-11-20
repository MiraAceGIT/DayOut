<?php
declare(strict_types=1);

namespace App\Model\Policies;

use App\Model\Dao\Event\EventRecord;
use App\Model\Dao\User\User;
use App\Model\Enums\EventStatus;
use App\Model\Exceptions\EventException;

/** Qui peut gerer quels events ; leve une EventException (403 / 404) sinon. */
final class EventPolicy
{
    public function __construct(private readonly User $users)
    {
    }

    /** Createur approuve ou admin, non banni : un createur banni perd aussi la main sur ses events. */
    public function assertCanManage(int $userId): void
    {
        if (!$this->users->canPublish($userId)) {
            throw EventException::creatorRequired();
        }
    }

    /** Event d'un autre : 403 s'il est publie, 404 sinon (un brouillon ne doit pas se reveler). */
    public function assertOwner(EventRecord $event, int $userId): void
    {
        if ($event->creatorId !== $userId) {
            throw $event->status === EventStatus::Published
                ? EventException::notOwner($event->id)
                : EventException::notFound($event->id);
        }
    }
}
