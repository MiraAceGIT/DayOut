<?php
declare(strict_types=1);

namespace Tests\Unit\Model\DomainServices;

use App\Core\ValidationException;
use App\Model\Dao\Event\EventRecord;
use App\Model\DomainServices\EventRules;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Enums\EventStatus;
use App\Model\Exceptions\EventException;
use DateTimeImmutable;
use Tests\TestCase;

/** Regles pures : aucune base, un EventRecord construit a la main suffit. */
final class EventRulesTest extends TestCase
{
    private static function event(EventStatus $status = EventStatus::Draft, int $registered = 0): EventRecord
    {
        return new EventRecord(
            id: 5, creatorId: 2, status: $status,
            startAt: '2030-01-01T20:00:00+00:00', endAt: '2030-01-02T02:00:00+00:00',
            isPaid: false, priceCents: 0, registeredCount: $registered,
        );
    }

    /** @param array<string, mixed> $body */
    private static function update(array $body): EventUpdateDto
    {
        return EventUpdateDto::fromArray($body);
    }

    public function testOnlyDraftAndPublishedEventsAreEditable(): void
    {
        EventRules::checkUpdate(self::event(EventStatus::Published), self::update(['title' => 'Ok']));

        foreach ([EventStatus::Cancelled, EventStatus::Archived] as $status) {
            $e = $this->assertThrows(EventException::class, fn () => EventRules::checkUpdate(self::event($status), self::update(['title' => 'x'])));
            $this->assertSame('EVENT_INVALID_STATUS', $e->errorCode);
        }
    }

    public function testFieldsLockedOncePublishedOrRegistered(): void
    {
        $published = $this->assertThrows(EventException::class,
            fn () => EventRules::checkUpdate(self::event(EventStatus::Published), self::update(['restricted_school_id' => 1])));
        $this->assertSame(['id' => 5, 'fields' => ['restricted_school_id']], $published->details);

        $registered = $this->assertThrows(EventException::class,
            fn () => EventRules::checkUpdate(self::event(EventStatus::Published, 3), self::update(['price_cents' => 500, 'is_paid' => true])));
        $this->assertSame(['is_paid', 'price_cents'], $registered->details['fields']);
    }

    public function testNewValuesAreCheckedAgainstTheStoredOnes(): void
    {
        $dates = $this->assertThrows(ValidationException::class,
            fn () => EventRules::checkUpdate(self::event(), self::update(['end_at' => '2030-01-01T19:00:00Z'])));
        $this->assertSame(['end_at'], array_keys($dates->errors));

        $price = $this->assertThrows(ValidationException::class,
            fn () => EventRules::checkUpdate(self::event(), self::update(['is_paid' => true])));
        $this->assertSame(['price_cents'], array_keys($price->errors));

        $capacity = $this->assertThrows(ValidationException::class,
            fn () => EventRules::checkUpdate(self::event(EventStatus::Published, 4), self::update(['capacity' => 3])));
        $this->assertSame(['capacity' => 'Must be at least 4 (current registrations)'], $capacity->errors);
    }

    public function testAnEventMustEndInTheFutureToBePublished(): void
    {
        EventRules::checkPublishable(self::event(), new DateTimeImmutable('2029-12-31T00:00:00Z'));
        $this->assertThrows(ValidationException::class,
            fn () => EventRules::checkPublishable(self::event(), new DateTimeImmutable('2030-01-02T02:00:00Z')));
    }
}
