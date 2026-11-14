<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Incident::class)]
final class IncidentTest extends TestCase
{
    public function testAnIncidentIsRecordedThenCompleted(): void
    {
        $discovered = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = Incident::record('List exported', $discovered, $discovered->modify('-1 day'), [DataCategory::fromString('email')], 120, true);

        self::assertMatchesRegularExpression(Incident::ID_PATTERN, $incident->id);
        self::assertSame(['authority', 'subjects'], $incident->pendingNotifications());
        self::assertFalse($incident->isClosed());
        self::assertSame('', $incident->measures);

        $notified = $incident->update(measures: 'Folder removed', authorityNotifiedAt: $discovered->modify('+1 day'));
        self::assertSame(['subjects'], $notified->pendingNotifications());
        self::assertSame('Folder removed', $notified->measures);
        self::assertSame($incident->id, $notified->id);
        self::assertSame(120, $notified->affected);
        self::assertSame('email', $notified->categories[0]->value);

        $closed = $notified->update(subjectsNotifiedAt: $discovered->modify('+2 days'), closedAt: $discovered->modify('+3 days'));
        self::assertSame([], $closed->pendingNotifications());
        self::assertTrue($closed->isClosed());
        self::assertSame('Folder removed', $closed->measures);
        self::assertEquals($discovered->modify('+1 day'), $closed->authorityNotifiedAt);

        $lowered = $closed->update(seriousRisk: false, affected: 3, categories: [DataCategory::fromString('phone')]);
        self::assertFalse($lowered->seriousRisk);
        self::assertSame(3, $lowered->affected);
        self::assertSame('phone', $lowered->categories[0]->value);
    }

    public function testANewMomentReplacesTheOneAlreadyKept(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(str_repeat('a', 32), 'x', $at, null, [], null, true, '', $at, $at, $at);

        $later = $incident->update(authorityNotifiedAt: $at->modify('+1 day'), subjectsNotifiedAt: $at->modify('+2 days'), closedAt: $at->modify('+3 days'));

        self::assertEquals($at->modify('+1 day'), $later->authorityNotifiedAt);
        self::assertEquals($at->modify('+2 days'), $later->subjectsNotifiedAt);
        self::assertEquals($at->modify('+3 days'), $later->closedAt);
    }

    public function testAnIncidentWithoutSeriousRiskOwesNoNotification(): void
    {
        $incident = Incident::record('A mail to the wrong person', new DateTimeImmutable('2026-11-10'), null, [], null, false, 'Asked them to delete it');

        self::assertSame([], $incident->pendingNotifications());
        self::assertNull($incident->affected);
        self::assertNull($incident->occurredAt);
        self::assertSame('Asked them to delete it', $incident->measures);
    }

    public function testTheSameMomentAsTheDiscoveryIsAllowed(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(str_repeat('a', 32), 'x', $at, $at, [], 0, true, str_repeat('é', 4000), $at, $at, $at);

        self::assertSame([], $incident->pendingNotifications());
        self::assertSame(0, $incident->affected);
        self::assertSame(4000, mb_strlen(new Incident(str_repeat('a', 32), str_repeat('é', 4000), $at, null, [], null, false)->summary));
    }

    /**
     * @return iterable<string, array{callable(): Incident, string}>
     */
    public static function refused(): iterable
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $before = $at->modify('-1 second');
        $id = str_repeat('a', 32);

        yield 'identifier' => [static fn (): Incident => new Incident('x', 'x', $at, null, [], null, false), 'An incident identifier is 32 lowercase hexadecimal characters.'];
        yield 'no summary' => [static fn (): Incident => new Incident($id, ' ', $at, null, [], null, false), 'An incident has a summary'];
        yield 'long summary' => [static fn (): Incident => new Incident($id, str_repeat('a', 4001), $at, null, [], null, false), 'An incident has a summary'];
        yield 'long measures' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, str_repeat('a', 4001)), 'An incident has a summary'];
        yield 'negative' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], -1, false), 'cannot be negative'];
        yield 'occurred after' => [static fn (): Incident => new Incident($id, 'x', $at, $at->modify('+1 second'), [], null, false), 'cannot have occurred after it was discovered'];
        yield 'authority before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, '', $before), 'cannot be marked "authority notified" before'];
        yield 'people before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, '', null, $before), 'cannot be marked "people notified" before'];
        yield 'closed before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, '', null, null, $before), 'cannot be marked "closed" before'];
        yield 'twice a category' => [static fn (): Incident => new Incident($id, 'x', $at, null, [DataCategory::fromString('email'), DataCategory::fromString('email')], null, false), 'lists the category "email" twice'];
    }

    /**
     * @param callable(): Incident $build
     */
    #[DataProvider('refused')]
    public function testWhatIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
