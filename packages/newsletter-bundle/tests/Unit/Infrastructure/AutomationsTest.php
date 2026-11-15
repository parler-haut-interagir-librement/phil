<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Run\RunId;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Event\LinkClicked;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Automations\AudienceTrigger;
use Phil\NewsletterBundle\Infrastructure\Automations\LinkClickedTrigger;
use Phil\NewsletterBundle\Infrastructure\Automations\Lists;
use Phil\NewsletterBundle\Infrastructure\Automations\SendAction;
use Phil\NewsletterBundle\Infrastructure\Automations\SubscribeAction;
use Phil\NewsletterBundle\Infrastructure\Automations\UnsubscribeAction;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(AudienceTrigger::class)]
#[CoversClass(LinkClickedTrigger::class)]
#[CoversClass(SubscribeAction::class)]
#[CoversClass(UnsubscribeAction::class)]
#[CoversClass(SendAction::class)]
#[CoversClass(Lists::class)]
final class AutomationsTest extends TestCase
{
    private Newsletter $newsletter;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
    }

    public function testAudienceTriggersMatchTheirTypeAndList(): void
    {
        $news = $this->newsletter->list('news');
        $trigger = new AudienceTrigger(AudienceEventType::Confirmed, $this->newsletter->lists);
        $event = new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', $news->id, 'u1', new DateTimeImmutable('@1800000000'));

        self::assertSame('newsletter.confirmed', $trigger->describe()->type);
        self::assertSame('Newsletter: subscriber confirmed', $trigger->describe()->label);
        self::assertSame([AudienceChanged::class], $trigger->events());
        $match = $trigger->match($event, Options::none());
        self::assertNotNull($match);
        self::assertSame('newsletter:'.$event->id(), $match->eventKey);
        self::assertSame('s1', $match->subject);
        self::assertSame(['external_id' => 'u1', 'list' => 'news', 'site' => 'default', 'subscriber' => 's1'], $match->payload->values);
        self::assertNotNull($trigger->match($event, Options::of(['list' => 'news'])));
        self::assertNull($trigger->match($event, Options::of(['list' => 'other'])));
        self::assertNull($trigger->match(new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', null, null, new DateTimeImmutable()), Options::of(['list' => 'news'])));
        self::assertSame(['list' => null], array_intersect_key((array) $trigger->match(new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', null, null, new DateTimeImmutable()), Options::none())?->payload->values, ['list' => 1]));
        self::assertNull($trigger->match(new AudienceChanged(AudienceEventType::Paused, 's1', 'default', null, null, new DateTimeImmutable()), Options::none()));
        self::assertNull($trigger->match(new stdClass(), Options::none()));
        self::assertCount(5, AudienceTrigger::TYPES);
    }

    public function testTheLinkClickedTrigger(): void
    {
        $trigger = new LinkClickedTrigger();
        $event = new LinkClicked('c1', 2, 's1', new DateTimeImmutable('@1800000000'));

        self::assertSame('newsletter.link_clicked', $trigger->describe()->type);
        self::assertSame([LinkClicked::class], $trigger->events());
        $match = $trigger->match($event, Options::of(['campaign' => 'c1']));
        self::assertNotNull($match);
        self::assertSame('newsletter-click:c1:2:s1:1800000000', $match->eventKey);
        self::assertSame(['campaign' => 'c1', 'link' => 2, 'subscriber' => 's1'], $match->payload->values);
        self::assertNotNull($trigger->match($event, Options::none()));
        self::assertNull($trigger->match($event, Options::of(['campaign' => 'c2'])));
        self::assertNull($trigger->match(new stdClass(), Options::none()));
    }

    public function testSubscribeAndItsCompensation(): void
    {
        $news = $this->newsletter->list('news', false);
        $events = $this->newsletter->list('events');
        $id = $this->subscriber($news, 'ada@example.com');
        $action = new SubscribeAction($this->newsletter->subscriptions, $this->newsletter->subscribers, $this->newsletter->lists);
        $context = $this->context($id, ['list' => 'events']);

        self::assertSame('newsletter.subscribe', $action->describe()->type);
        self::assertTrue($action->describe()->compensable);
        $result = $action->execute($context);
        self::assertSame(['joined' => true, 'list' => $events->id], $result->values);
        self::assertSame(['joined' => false, 'list' => $events->id], $action->execute($context)->values);

        $action->compensate($context, Payload::of(['joined' => false, 'list' => $events->id]));
        self::assertSame(MembershipStatus::Active, $this->membershipStatus($id, $events->id));
        $action->compensate($this->context(null, ['list' => 'events']), $result);
        $action->compensate($context, Payload::of(['joined' => true]));
        self::assertSame(MembershipStatus::Active, $this->membershipStatus($id, $events->id));
        $action->compensate($context, $result);
        self::assertSame(MembershipStatus::Unsubscribed, $this->membershipStatus($id, $events->id));
    }

    public function testUnsubscribeAndItsCompensation(): void
    {
        $news = $this->newsletter->list('news', false);
        $events = $this->newsletter->list('events', false);
        $id = $this->subscriber($news, 'ada@example.com');
        $this->newsletter->subscriptions->subscribe($events, 'ada@example.com');
        $action = new UnsubscribeAction($this->newsletter->subscriptions, $this->newsletter->subscribers, $this->newsletter->lists);
        $context = $this->context($id, ['list' => 'events']);

        self::assertSame('newsletter.unsubscribe', $action->describe()->type);
        $result = $action->execute($context);
        self::assertSame(['left' => true, 'list' => $events->id], $result->values);
        self::assertSame(['left' => false, 'list' => $events->id], $action->execute($context)->values);
        $action->compensate($context, Payload::of(['left' => false, 'list' => $events->id]));
        $action->compensate($context, Payload::of(['left' => true, 'list' => 'missing']));
        $action->compensate($this->context(null, []), $result);
        self::assertSame(MembershipStatus::Unsubscribed, $this->membershipStatus($id, $events->id));
        $action->compensate($context, $result);
        self::assertSame(MembershipStatus::Active, $this->membershipStatus($id, $events->id));
    }

    public function testAnActionNeedsASubscriberAndAList(): void
    {
        $news = $this->newsletter->list('news', false);
        $id = $this->subscriber($news, 'ada@example.com');
        $action = new SubscribeAction($this->newsletter->subscriptions, $this->newsletter->subscribers, $this->newsletter->lists);

        foreach ([[$this->context(null, ['list' => 'news']), 'The run is about no subscriber of the newsletter.'], [$this->context('nobody', ['list' => 'news']), 'The run is about no subscriber of the newsletter.'], [$this->context($id, ['list' => 'none']), "The subscriber's site has no list of that key."], [$this->context($id, []), "The subscriber's site has no list of that key."]] as [$context, $message]) {
            try {
                $action->execute($context);
                self::fail('Expected a failure.');
            } catch (ActionFailed $failed) {
                self::assertFalse($failed->retryable);
                self::assertSame($message, $failed->getMessage());
            }
        }
    }

    public function testSendQueuesTheTemplateOncePerStep(): void
    {
        $news = $this->newsletter->list('news', false);
        $id = $this->subscriber($news, 'ada@example.com');
        $action = new SendAction($this->newsletter->subscribers, $this->newsletter->deliveries, $this->newsletter->bus, $this->newsletter->clock);
        $context = $this->context($id, ['template' => 'welcome']);

        self::assertSame('newsletter.send', $action->describe()->type);
        self::assertFalse($action->describe()->compensable);
        $first = $action->execute($context);
        $second = $action->execute($context);

        self::assertSame(['delivery' => Delivery::idForKey('run/step')], $first->values);
        self::assertSame($first->values, $second->values);
        self::assertEquals([new SendDelivery(Delivery::idForKey('run/step'))], $this->newsletter->bus->messages(SendDelivery::class));
        $delivery = $this->newsletter->deliveries->get(Delivery::idForKey('run/step'));
        self::assertSame([$id, null, 'welcome'], [$delivery?->subscriberId, $delivery?->campaignId, $delivery?->template]);

        $this->expectException(ActionFailed::class);
        $action->execute($this->context('nobody', ['template' => 'welcome']));
    }

    private function subscriber(MailingList $list, string $email): string
    {
        $this->newsletter->subscriptions->subscribe($list, $email);

        return $this->newsletter->subscribers->byEmail(Site::default(), $email)->id ?? '';
    }

    private function membershipStatus(string $subscriberId, string $listId): ?MembershipStatus
    {
        return $this->newsletter->subscribers->membership($subscriberId, $listId)?->status;
    }

    /**
     * @param array<string, string> $options
     */
    private function context(?string $subject, array $options): ActionContext
    {
        return new ActionContext(RunId::fromString(str_repeat('a', 32)), AutomationId::fromString('a'), 'step', Options::of($options), Payload::empty(), $subject, [], 'run/step');
    }

    public function testTheComponentsDescribeTheirOptions(): void
    {
        $subscriptions = $this->newsletter->subscriptions;
        $subscribers = $this->newsletter->subscribers;
        $lists = $this->newsletter->lists;

        self::assertEquals([new OptionSpec('list', OptionType::Identifier, false, 'List key')], new AudienceTrigger(AudienceEventType::Confirmed, $lists)->describe()->options);
        self::assertEquals([new OptionSpec('campaign', OptionType::Identifier, false, 'Campaign')], new LinkClickedTrigger()->describe()->options);
        self::assertEquals([new OptionSpec('list', OptionType::Identifier, true, 'List key')], new SubscribeAction($subscriptions, $subscribers, $lists)->describe()->options);
        self::assertEquals([new OptionSpec('list', OptionType::Identifier, true, 'List key')], new UnsubscribeAction($subscriptions, $subscribers, $lists)->describe()->options);
        self::assertTrue(new UnsubscribeAction($subscriptions, $subscribers, $lists)->describe()->compensable);
        self::assertEquals([new OptionSpec('template', OptionType::Identifier, true, 'Template key')], new SendAction($subscribers, $this->newsletter->deliveries, $this->newsletter->bus, $this->newsletter->clock)->describe()->options);
    }

    public function testSendQueuesTheTemplateOnlyTheFirstTime(): void
    {
        $news = $this->newsletter->list('news', false);
        $id = $this->subscriber($news, 'ada@example.com');
        $action = new SendAction($this->newsletter->subscribers, $this->newsletter->deliveries, $this->newsletter->bus, $this->newsletter->clock);
        $context = $this->context($id, ['template' => 'welcome']);

        $action->execute($context);
        self::assertCount(1, $this->newsletter->bus->messages(SendDelivery::class));

        $action->execute($context);
        self::assertCount(1, $this->newsletter->bus->messages(SendDelivery::class));
    }
}
