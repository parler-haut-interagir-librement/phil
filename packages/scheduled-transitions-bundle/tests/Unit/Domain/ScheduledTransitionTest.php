<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Domain\Exception\InvalidSchedule;
use Phil\ScheduledTransitionsBundle\Domain\Exception\NoHandler;
use Phil\ScheduledTransitionsBundle\Domain\Exception\SchedulerException;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;
use Phil\ScheduledTransitionsBundle\Domain\Transition\TransitionStatus;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScheduledTransition::class)]
#[CoversClass(InvalidSchedule::class)]
#[CoversClass(NoHandler::class)]
final class ScheduledTransitionTest extends TestCase
{
    public function testATransitionWaitsForItsMoment(): void
    {
        $at = new DateTimeImmutable('2026-11-09 09:00', new DateTimeZone('America/Toronto'));
        $transition = ScheduledTransition::of(ResourceReference::of('page', '42'), 'publish', $at);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $transition->id);
        self::assertSame(['page:42', 'publish', '2026-11-09T14:00:00+00:00', TransitionStatus::Pending, 0, null, null], [$transition->resource()->toString(), $transition->transition(), $transition->dueAt()->format('c'), $transition->status(), $transition->attempts(), $transition->lastError(), $transition->appliedAt()]);
        self::assertFalse($transition->isDueAt(new DateTimeImmutable('2026-11-09 13:59:59 UTC')));
        self::assertTrue($transition->isDueAt(new DateTimeImmutable('2026-11-09 14:00:00 UTC')));

        $transition->reschedule(new DateTimeImmutable('2026-11-10 09:00', new DateTimeZone('America/Toronto')));
        self::assertSame('2026-11-10T14:00:00+00:00', $transition->dueAt()->format('c'));

        $transition->failed('Locked.', 3);
        self::assertSame([TransitionStatus::Pending, 1, 'Locked.'], [$transition->status(), $transition->attempts(), $transition->lastError()]);
        $transition->applied(new DateTimeImmutable('2026-11-10 09:01', new DateTimeZone('America/Toronto')));
        self::assertSame([TransitionStatus::Done, 2, null, '2026-11-10T14:01:00+00:00'], [$transition->status(), $transition->attempts(), $transition->lastError(), $transition->appliedAt()?->format('c')]);
        self::assertFalse($transition->isDueAt(new DateTimeImmutable('2030-01-01 UTC')));
        self::assertSame('a_9', ScheduledTransition::of(ResourceReference::of('page', '1'), 'a_9', $at)->transition());
        self::assertSame(str_repeat('a', 64), ScheduledTransition::of(ResourceReference::of('page', '1'), str_repeat('a', 64), $at)->transition());
    }

    public function testAttemptsRunOut(): void
    {
        $transition = ScheduledTransition::of(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable());
        $transition->failed(str_repeat('é', 300), 2);
        self::assertSame([TransitionStatus::Pending, 255], [$transition->status(), mb_strlen((string) $transition->lastError())]);
        $transition->failed('Again.', 2);
        self::assertSame([TransitionStatus::Failed, 2, 'Again.'], [$transition->status(), $transition->attempts(), $transition->lastError()]);

        $cancelled = ScheduledTransition::of(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable());
        $cancelled->cancel();
        self::assertSame(TransitionStatus::Cancelled, $cancelled->status());

        foreach ([$transition->cancel(...), static fn () => $transition->reschedule(new DateTimeImmutable()), static fn () => $transition->applied(new DateTimeImmutable()), static fn () => $transition->failed('x', 9)] as $change) {
            try {
                $change();
                self::fail('A transition no longer pending changed.');
            } catch (InvalidSchedule $invalidSchedule) {
                self::assertSame('The transition "publish" of page:1 is failed, no longer pending.', $invalidSchedule->getMessage());
            }
        }
    }

    public function testNamesAreChecked(): void
    {
        foreach (['Publish', '1publish', 'pub-lish', str_repeat('a', 65), "publish\n", ''] as $name) {
            try {
                ScheduledTransition::of(ResourceReference::of('page', '1'), $name, new DateTimeImmutable());
                self::fail($name);
            } catch (InvalidSchedule $invalidSchedule) {
                self::assertSame(\sprintf('A transition is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $name), $invalidSchedule->getMessage());
                self::assertInstanceOf(SchedulerException::class, $invalidSchedule);
            }
        }

        self::assertSame(['a.b-c_9', str_repeat('1', 255), 'p'.str_repeat('a', 63)], [ResourceReference::of('a.b-c_9', '1')->key, ResourceReference::of('page', str_repeat('1', 255))->id, ResourceReference::of('p'.str_repeat('a', 63), '1')->key]);
        self::assertTrue(ResourceReference::of('page', '1')->equals(ResourceReference::of('page', '1')));
        self::assertFalse(ResourceReference::of('page', '1')->equals(ResourceReference::of('page', '2')));
        self::assertFalse(ResourceReference::of('page', '1')->equals(ResourceReference::of('post', '1')));
        self::assertSame('No handler applies "publish" to page:1: register a TransitionHandler for it.', NoHandler::for('page:1', 'publish')->getMessage());
        self::assertInstanceOf(SchedulerException::class, NoHandler::for('page:1', 'publish'));
    }

    public function testTheTable(): void
    {
        $metadata = Database::entityManager()->getClassMetadata(ScheduledTransition::class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame(['phil_scheduled_transition', [
            'id' => ['id', 'string', 36, false, true],
            'resourceKind' => ['resource_kind', 'string', 64, false, null],
            'resourceId' => ['resource_id', 'string', 255, false, null],
            'transition' => ['transition', 'string', 64, false, null],
            'dueAt' => ['due_at', 'datetime_immutable', null, false, null],
            'status' => ['status', 'string', 16, false, null],
            'attempts' => ['attempts', 'integer', null, false, null],
            'lastError' => ['last_error', 'string', 255, true, null],
            'appliedAt' => ['applied_at', 'datetime_immutable', null, true, null],
            'claimedUntil' => ['claimed_until', 'datetime_immutable', null, true, null],
        ]], [$metadata->getTableName(), $columns]);
        $indexes = [];

        foreach ($metadata->table['indexes'] ?? [] as $index) {
            self::assertIsArray($index);
            $indexes[] = $index['columns'] ?? [];
        }

        self::assertSame([['status', 'due_at'], ['resource_kind', 'resource_id']], $indexes);
        self::assertSame(TransitionStatus::class, $metadata->fieldMappings['status']->enumType);
        self::assertSame(36, \strlen(ScheduledTransition::of(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable())->id));
    }
}
