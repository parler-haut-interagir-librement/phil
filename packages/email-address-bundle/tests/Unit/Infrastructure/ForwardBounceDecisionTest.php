<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;
use Phil\EmailAddressBundle\Infrastructure\Messenger\ForwardBounceDecision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ForwardBounceDecision::class)]
#[CoversClass(BounceDecidedMessage::class)]
final class ForwardBounceDecisionTest extends TestCase
{
    public function testADecisionIsSentOnWithIdentifiersOnly(): void
    {
        $bus = $this->bus();
        $fingerprint = AddressFingerprint::of('ada@example.org', 'key');
        $notice = new BounceNotice(NoticeKind::Hard, NoticeSource::Webhook, 'webhook:evt-1', 'ada@example.org', $fingerprint, true, new DateTimeImmutable(), 'm-42');

        new ForwardBounceDecision($bus)(new BounceDecided($notice, BounceDecision::Suspend));

        self::assertEquals([new BounceDecidedMessage('suspend', 'hard', $fingerprint->value, 'm-42', $notice->key())], $bus->messages);
        self::assertStringNotContainsString('ada@example.org', serialize($bus->messages));
    }

    public function testADecisionWithoutAnAddressIsSentOnWithoutAFingerprint(): void
    {
        $bus = $this->bus();
        $notice = new BounceNotice(NoticeKind::Complaint, NoticeSource::Webhook, 'webhook:evt-2', null, null, false, new DateTimeImmutable(), 'm-43');

        new ForwardBounceDecision($bus)(new BounceDecided($notice, BounceDecision::Review));

        self::assertEquals([new BounceDecidedMessage('review', 'complaint', null, 'm-43', $notice->key())], $bus->messages);
    }

    public function testADuplicateIsNotSentOn(): void
    {
        $bus = $this->bus();
        $notice = new BounceNotice(NoticeKind::Hard, NoticeSource::Webhook, 'webhook:evt-1', null, null, true, new DateTimeImmutable());

        new ForwardBounceDecision($bus)(new BounceDecided($notice, BounceDecision::Duplicate));

        self::assertSame([], $bus->messages);
    }

    /**
     * @return MessageBusInterface&object{messages: list<object>}
     */
    private function bus(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
    }
}
