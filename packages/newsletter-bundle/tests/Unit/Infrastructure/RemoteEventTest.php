<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Phil\BounceParser\BounceParser;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[CoversClass(MailerEventConsumer::class)]
final class RemoteEventTest extends TestCase
{
    public function testAProvidersEventsBounceComplainOrUnsubscribe(): void
    {
        $newsletter = new Newsletter();
        $list = $newsletter->list('news', false);
        $ids = [];

        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $name) {
            $newsletter->subscriptions->subscribe($list, $name.'@example.com');
            $ids[$name] = $newsletter->subscribers->byEmail(Site::default(), $name.'@example.com')->id ?? '';
        }

        $newsletter->deliveries->create(new Delivery('d1', $ids['a'], null, 't', $newsletter->clock->now()));
        $consumer = new MailerEventConsumer(new BounceHandler(new BounceParser(), $newsletter->subscribers, $newsletter->deliveries, $newsletter->suppressions, $newsletter->subscriptions, new ConfiguredSite(), $newsletter->clock, softBounceThreshold: 1));

        $consumer->consume($this->delivery(MailerDeliveryEvent::BOUNCE, 'other@example.com', [MessageComposer::METADATA => 'd1']));
        $consumer->consume($this->delivery(MailerDeliveryEvent::DROPPED, 'b@example.com'));
        $consumer->consume($this->delivery(MailerDeliveryEvent::DEFERRED, 'c@example.com', [MessageComposer::METADATA => 42]));
        $consumer->consume($this->delivery(MailerDeliveryEvent::DELIVERED, 'f@example.com'));
        $consumer->consume($this->engagement(MailerEngagementEvent::SPAM, 'd@example.com'));
        $consumer->consume($this->engagement(MailerEngagementEvent::UNSUBSCRIBE, 'e@example.com'));
        $consumer->consume($this->engagement(MailerEngagementEvent::OPEN, 'f@example.com'));
        $consumer->consume(new RemoteEvent('bounce', 'x', []));

        $status = static fn (string $name): ?SubscriberStatus => $newsletter->subscribers->get($ids[$name])?->status;
        self::assertSame(
            [SubscriberStatus::Bounced, SubscriberStatus::Bounced, SubscriberStatus::Bounced, SubscriberStatus::Complained, SubscriberStatus::Unsubscribed, SubscriberStatus::Active],
            array_map($status, ['a', 'b', 'c', 'd', 'e', 'f']),
        );
        self::assertInstanceOf(Subscriber::class, $newsletter->subscribers->get($ids['f']));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function delivery(string $name, string $email, array $metadata = []): MailerDeliveryEvent
    {
        $event = new MailerDeliveryEvent($name, 'id', []);
        $event->setRecipientEmail($email);
        $event->setMetadata($metadata);

        return $event;
    }

    private function engagement(string $name, string $email): MailerEngagementEvent
    {
        $event = new MailerEngagementEvent($name, 'id', []);
        $event->setRecipientEmail($email);

        return $event;
    }
}
