<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutgoingMessage::class)]
#[CoversClass(ReceivedMessage::class)]
final class OutgoingMessageTest extends TestCase
{
    public function testAMessageIsAddressedToItsPartner(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00.123456+00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', ['sku' => 'TEA-1', 'name' => 'Thé « vert »'], $at);

        self::assertMatchesRegularExpression('/^msg_[1-9A-HJ-NP-Za-km-z]{22}$/D', $message->id);
        self::assertNotSame($message->id, OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at)->id);
        self::assertSame(['reseller', 'product.updated', ['sku' => 'TEA-1', 'name' => 'Thé « vert »'], $at, 0, $at, null, null], [
            $message->partner, $message->type, $message->data, $message->createdAt, $message->attempts(), $message->nextAttemptAt(), $message->deliveredAt(), $message->lastError(),
        ]);
        self::assertSame('{"type":"product.updated","timestamp":"2026-11-03T10:00:00.123456+00:00","audience":"reseller","data":{"sku":"TEA-1","name":"Thé « vert »"}}', $message->body());
    }

    public function testEmptyDataIsAnObject(): void
    {
        $message = OutgoingMessage::to(Partners::reseller(), 'ping.sent', [], new DateTimeImmutable('2026-11-03 10:00:00+00:00'));

        self::assertStringEndsWith('"data":{}}', $message->body());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTypes(): iterable
    {
        yield 'no dot' => ['product'];
        yield 'uppercase' => ['Product.updated'];
        yield 'a digit first' => ['1product.updated'];
        yield 'a trailing newline' => ["product.updated\n"];
        yield 'sixty-five characters' => ['a.'.str_repeat('b', 63)];
    }

    #[DataProvider('badTypes')]
    public function testATypeIsADottedName(string $type): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a message type: a dotted name such as "product.updated".', $type));

        OutgoingMessage::to(Partners::reseller(), $type, [], new DateTimeImmutable());
    }

    public function testATypeHoldsUpTo64Characters(): void
    {
        $type = 'a.'.str_repeat('b', 62);

        self::assertSame($type, OutgoingMessage::to(Partners::reseller(), $type, [], new DateTimeImmutable())->type);
    }

    public function testAMessageIsDueFromItsCreation(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);

        self::assertFalse($message->isDue($at->modify('-1 second')));
        self::assertTrue($message->isDue($at));
        self::assertTrue($message->isDue($at->modify('+1 second')));
    }

    public function testAFailedAttemptWaitsTwiceAsLongAsTheOneBefore(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);

        $message->failed($at, 'HTTP 500.', 8);
        self::assertSame([1, '2026-11-03 10:01:00', 'HTTP 500.'], [$message->attempts(), $message->nextAttemptAt()?->format('Y-m-d H:i:s'), $message->lastError()]);
        self::assertFalse($message->isDue($at->modify('+59 seconds')));
        self::assertTrue($message->isDue($at->modify('+60 seconds')));

        $message->failed($at, 'No answer.', 8);
        self::assertSame([2, '2026-11-03 10:02:00', 'No answer.'], [$message->attempts(), $message->nextAttemptAt()?->format('Y-m-d H:i:s'), $message->lastError()]);

        $message->failed($at, 'HTTP 503.', 8);
        self::assertSame([3, '2026-11-03 10:04:00'], [$message->attempts(), $message->nextAttemptAt()?->format('Y-m-d H:i:s')]);
    }

    public function testTheLastAllowedAttemptGivesTheMessageUp(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);

        $message->failed($at, 'HTTP 500.', 2);
        self::assertNotNull($message->nextAttemptAt());

        $message->failed($at, 'HTTP 500.', 2);
        self::assertNull($message->nextAttemptAt());
        self::assertFalse($message->isDue($at->modify('+1 year')));
    }

    public function testOneAttemptMayBeAllowed(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);

        $message->failed($at, 'HTTP 500.', 1);

        self::assertNull($message->nextAttemptAt());
    }

    public function testTheErrorIsCutTo255Characters(): void
    {
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], new DateTimeImmutable());

        $message->failed(new DateTimeImmutable(), str_repeat('é', 300), 8);

        self::assertSame(str_repeat('é', 255), $message->lastError());

        $refused = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], new DateTimeImmutable());
        $refused->refused(new DateTimeImmutable(), str_repeat('é', 300));

        self::assertSame(str_repeat('é', 255), $refused->lastError());
    }

    public function testADeliveredMessageIsNoLongerDue(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);
        $message->failed($at, 'HTTP 500.', 8);

        $message->delivered($at->modify('+1 minute'));

        self::assertSame([2, '2026-11-03 10:01:00', null, null], [$message->attempts(), $message->deliveredAt()?->format('Y-m-d H:i:s'), $message->nextAttemptAt(), $message->lastError()]);
        self::assertFalse($message->isDue($at->modify('+1 year')));
    }

    /**
     * The address of a forwarded order leaves the message once it is delivered or given up; the rest stays.
     */
    public function testAnEndedMessageForgetsItsPersonalData(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $data = ['order' => 'A-1', 'lines' => [], 'ship_to' => ['name' => 'Ada Lovelace']];

        $delivered = OutgoingMessage::to(Partners::supplier(), 'order.forwarded', $data, $at);
        self::assertStringContainsString('Ada Lovelace', $delivered->body());
        $delivered->delivered($at);
        self::assertSame(['order' => 'A-1', 'lines' => []], $delivered->data);
        self::assertStringNotContainsString('Ada', $delivered->body());

        $failed = OutgoingMessage::to(Partners::supplier(), 'order.forwarded', $data, $at);
        $failed->failed($at, 'HTTP 500.', 2);
        self::assertSame($data, $failed->data);
        self::assertNull($failed->givenUpAt());
        $later = $at->modify('+1 minute');
        $failed->failed($later, 'HTTP 500.', 2);
        self::assertSame([['order' => 'A-1', 'lines' => []], $later], [$failed->data, $failed->givenUpAt()]);

        $refused = OutgoingMessage::to(Partners::supplier(), 'order.forwarded', $data, $at);
        $refused->claim($at->modify('+30 minutes'));
        $refused->refused($at, 'HTTP 400.');
        self::assertSame([['order' => 'A-1', 'lines' => []], $at, null, null, 1, 'HTTP 400.'], [$refused->data, $refused->givenUpAt(), $refused->nextAttemptAt(), $refused->claimedUntil(), $refused->attempts(), $refused->lastError()]);
        self::assertFalse($refused->isDue($at->modify('+1 year')));
    }

    public function testAClaimedMessageIsNotDueUntilTheClaimLapses(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);

        $message->claim($at->modify('+30 minutes'));

        self::assertSame([true, false], [$message->isClaimed($at), $message->isDue($at)]);
        self::assertSame([false, true], [$message->isClaimed($at->modify('+30 minutes')), $message->isDue($at->modify('+30 minutes'))]);
        $message->failed($at, 'HTTP 500.', 8);
        self::assertNull($message->claimedUntil());
        $message->claim($at->modify('+30 minutes'));
        $message->delivered($at);
        self::assertNull($message->claimedUntil());
    }

    public function testAReceivedMessageIsRemembered(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $received = ReceivedMessage::of('msg_1', Partners::supplier(), 'product.updated', $at);

        self::assertSame(['msg_1', 'supplier', 'product.updated', $at], [$received->id, $received->partner, $received->type, $received->receivedAt]);
        self::assertSame(str_repeat('é', 64), ReceivedMessage::of('msg_2', Partners::supplier(), str_repeat('é', 70), $at)->type);
    }
}
