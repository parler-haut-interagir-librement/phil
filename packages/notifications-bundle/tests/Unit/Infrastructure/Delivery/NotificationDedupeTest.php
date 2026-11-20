<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure\Delivery;

use ArrayObject;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\NotificationsBundle\Application\Delivery\DedupeTicket;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Infrastructure\Idempotency\NotificationDedupe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(NotificationDedupe::class)]
#[CoversClass(DedupeTicket::class)]
final class NotificationDedupeTest extends TestCase
{
    private MockClock $clock;

    private NotificationDedupe $dedupe;

    private NotificationType $type;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 12:00:00 UTC');
        $this->dedupe = new NotificationDedupe(new IdempotencyGuard(new CacheIdempotencyStore(new ArrayAdapter(clock: $this->clock), new LockFactory(new InMemoryStore()), $this->clock), $this->clock));
        $this->type = new NotificationType('comment.replied');
    }

    public function testTheWindowIsFiveMinutesByDefault(): void
    {
        self::assertSame(300, NotificationDedupe::TTL);
        $this->dedupe->complete($this->reserved($this->message()), $this->type);
        $this->clock->sleep(299);

        $within = $this->begin($this->message(), $this->type, '42');

        self::assertTrue($within?->duplicate);
        self::assertNull($within->reservation);

        $this->clock->sleep(1);
        $after = $this->begin($this->message(), $this->type, '42');

        self::assertFalse($after?->duplicate);
        self::assertInstanceOf(IdempotencyReservation::class, $after->reservation);
    }

    public function testWhatMakesTwoNotificationsTheSame(): void
    {
        $this->dedupe->complete($this->reserved($this->message()), $this->type);

        foreach ([
            'another subject' => new SendNotification('comment.replied', ['42'], null, 'comment', '10', ['event' => 7], '17', 'events'),
            'another data' => new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 8], '17', 'events'),
            'another actor' => new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '18', 'events'),
            'another origin' => new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '17', 'shop'),
            'another subject type' => new SendNotification('comment.replied', ['42'], null, 'post', '9', ['event' => 7], '17', 'events'),
            'a key' => new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '17', 'events', true, 'k'),
        ] as $what => $message) {
            self::assertInstanceOf(IdempotencyReservation::class, $this->begin($message, $this->type, '42')?->reservation, $what);
        }

        self::assertInstanceOf(IdempotencyReservation::class, $this->begin($this->message(), $this->type, '43')?->reservation, 'another recipient');
        self::assertInstanceOf(IdempotencyReservation::class, $this->begin($this->message(), new NotificationType('comment.liked'), '42')?->reservation, 'another type');
        self::assertTrue($this->begin($this->message(), $this->type, '42')?->duplicate);
        self::assertTrue($this->begin(new SendNotification('comment.replied', ['42', '43'], null, 'comment', '9', ['event' => 7], '17', 'events', false), $this->type, '42')?->duplicate, 'the broadcast and the other recipients do not count');
    }

    public function testAReleasedDeliveryRunsAgain(): void
    {
        $this->dedupe->release($this->reserved($this->message()), $this->type);

        self::assertInstanceOf(IdempotencyReservation::class, $this->begin($this->message(), $this->type, '42')?->reservation);
    }

    public function testADuplicateHasNothingToCompleteNorRelease(): void
    {
        $this->dedupe->complete($this->reserved($this->message()), $this->type);
        $duplicate = $this->begin($this->message(), $this->type, '42');
        self::assertInstanceOf(DedupeTicket::class, $duplicate);

        $this->dedupe->complete($duplicate, $this->type);
        $this->dedupe->release($duplicate, $this->type);

        self::assertTrue($this->begin($this->message(), $this->type, '42')?->duplicate);
    }

    public function testADeliveryThatOutlivedItsReservationLeavesTheKeyToTheNextRun(): void
    {
        $first = $this->reserved($this->message());
        $this->clock->sleep(301);
        $second = $this->reserved($this->message());

        $this->dedupe->complete($first, $this->type);
        $this->dedupe->release($first, $this->type);

        self::assertTrue($this->begin($this->message(), $this->type, '42')?->duplicate);
        $this->dedupe->complete($second, $this->type);
        self::assertTrue($this->begin($this->message(), $this->type, '42')?->duplicate);
    }

    public function testATypeWithoutWindowIsNeverADuplicate(): void
    {
        $type = new NotificationType('comment.replied', dedupeTtl: 0);
        $runs = 0;

        self::assertNull($this->begin($this->message(), $type, '42'));
        self::assertTrue($this->dedupe->once($this->message(), $type, '42', static function () use (&$runs): void { ++$runs; }));
        self::assertTrue($this->dedupe->once($this->message(), $type, '42', static function () use (&$runs): void { ++$runs; }));
        self::assertSame(2, $runs);
    }

    public function testTheCentreKeepsItOncePerWindow(): void
    {
        $runs = new ArrayObject();
        $record = static function () use ($runs): void { $runs->append(true); };

        self::assertTrue($this->dedupe->once($this->message(), $this->type, '42', $record));
        self::assertFalse($this->dedupe->once($this->message(), $this->type, '42', $record));
        self::assertCount(1, $runs);
        self::assertInstanceOf(IdempotencyReservation::class, $this->begin($this->message(), $this->type, '42')?->reservation, 'the delivery has a key of its own');

        $this->clock->sleep(301);

        self::assertTrue($this->dedupe->once($this->message(), $this->type, '42', $record));
        self::assertCount(2, $runs);
    }

    public function testTheCentreTriesAgainAfterAFailure(): void
    {
        try {
            $this->dedupe->once($this->message(), $this->type, '42', static function (): never { throw new RuntimeException('The table is locked.'); });
            self::fail('The failure goes up.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The table is locked.', $runtimeException->getMessage());
        }

        self::assertTrue($this->dedupe->once($this->message(), $this->type, '42', static function (): void {}));
    }

    public function testTheWindowRunsFromTheCompletionOfTheDelivery(): void
    {
        $ticket = $this->reserved($this->message());
        $this->clock->sleep(200);
        $this->dedupe->complete($ticket, $this->type);
        $this->clock->sleep(200);

        self::assertTrue($this->begin($this->message(), $this->type, '42')?->duplicate);
    }

    public function testTheWindowOfTheCentreRunsFromTheEndOfItsRecording(): void
    {
        $this->dedupe->once($this->message(), $this->type, '42', fn () => $this->clock->sleep(200));
        $this->clock->sleep(200);

        self::assertFalse($this->dedupe->once($this->message(), $this->type, '42', static function (): void {}));
    }

    public function testTheKeyIsTheHashOfTheTypeTheRecipientAndWhatMakesTheMessage(): void
    {
        $hash = static fn (string $source): string => hash('sha256', implode("\0", ['comment.replied', '42', $source]));
        $content = 'content:'.json_encode(['comment', '9', ['event' => 7], '17', 'events'], \JSON_THROW_ON_ERROR);
        $keyed = new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '17', 'events', true, 'k');

        self::assertSame($hash($content), $this->keyOf($this->reserved($this->message())));
        self::assertSame($hash('key:k'), $this->keyOf($this->reserved($keyed)));
    }

    public function testTheWindowOfTheTypeWins(): void
    {
        $type = new NotificationType('comment.replied', dedupeTtl: 10);
        $decision = $this->begin($this->message(), $type, '42');
        self::assertInstanceOf(DedupeTicket::class, $decision);
        $this->dedupe->complete($decision, $type);
        $this->clock->sleep(10);

        self::assertInstanceOf(IdempotencyReservation::class, $this->begin($this->message(), $type, '42')?->reservation);
    }

    /**
     * @phpstan-impure
     */
    private function begin(SendNotification $message, NotificationType $type, string $recipient): ?DedupeTicket
    {
        return $this->dedupe->begin($message, $type, $recipient);
    }

    private function keyOf(DedupeTicket $ticket): string
    {
        self::assertInstanceOf(IdempotencyReservation::class, $ticket->reservation);

        return $ticket->reservation->key->value;
    }

    private function reserved(SendNotification $message): DedupeTicket
    {
        $decision = $this->begin($message, $this->type, '42');
        self::assertInstanceOf(IdempotencyReservation::class, $decision?->reservation);

        return $decision;
    }

    private function message(): SendNotification
    {
        return new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '17', 'events');
    }
}
