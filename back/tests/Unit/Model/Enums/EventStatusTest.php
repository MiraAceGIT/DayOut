<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Enums;

use App\Model\Enums\EventStatus;
use Tests\TestCase;

final class EventStatusTest extends TestCase
{
    public function testLifecycleRules(): void
    {
        $editable = array_filter(EventStatus::cases(), static fn (EventStatus $s): bool => $s->isEditable());
        $this->assertSame([EventStatus::Draft, EventStatus::Published], array_values($editable));

        $deletable = array_filter(EventStatus::cases(), static fn (EventStatus $s): bool => $s->isDeletable());
        $this->assertSame([EventStatus::Draft], array_values($deletable));

        $this->assertSame(EventStatus::Draft, EventStatus::Published->requiredFrom());
        $this->assertSame(EventStatus::Published, EventStatus::Cancelled->requiredFrom());
        $this->assertSame(null, EventStatus::Draft->requiredFrom());
        $this->assertSame(null, EventStatus::Archived->requiredFrom());
    }
}
