<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use LogicException;
use Phil\BounceParser\Verp;
use Phil\EmailAddress\Dns\StaticResolver;
use Phil\EmailAddress\MailDomain;
use Phil\NewsletterBundle\Application\ApprovalPolicy;
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Handler\DispatchDueCampaignsHandler;
use Phil\NewsletterBundle\Application\Handler\SendCampaignHandler;
use Phil\NewsletterBundle\Application\Handler\SendConfirmationHandler;
use Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler;
use Phil\NewsletterBundle\Application\ListRecipients;
use Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Application\Message\SendConfirmation;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfile;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Application\SubscribeOutcome;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\SendRun;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\SendWindow;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\NewsletterBundle\Tests\Unit\Fixture\ScriptedThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Mime\Email;

#[CoversClass(SendCampaignHandler::class)]
#[CoversClass(SendDeliveryHandler::class)]
#[CoversClass(SendConfirmationHandler::class)]
#[CoversClass(DispatchDueCampaignsHandler::class)]
#[CoversClass(Campaigns::class)]
#[CoversClass(ListRecipients::class)]
#[CoversClass(MessageComposer::class)]
#[CoversClass(SenderProfile::class)]
#[CoversClass(SenderProfiles::class)]
#[CoversClass(NewsletterUrls::class)]
final class SendingTest extends TestCase
{
    private Newsletter $newsletter;

    private ScriptedThrottle $throttle;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
        $this->throttle = new ScriptedThrottle();
    }

    public function testACampaignIsQueuedOncePerRecipientThenEachMessageIsSent(): void
    {
        $list = $this->newsletter->list('news', false);
        $ada = $this->subscribe($list, 'ada@example.com');
        $bob = $this->subscribe($list, 'bob@example.org');
        $this->subscribe($this->newsletter->list('other', false), 'carl@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', "# News\n\n[Read it](https://example.com/news) or [mail](mailto:a@example.com)", 'A preheader');
        $this->newsletter->campaigns->save($campaign);

        $this->campaigns()->sendNow($campaign);
        self::assertEquals([new SendCampaign('c1', SendRun::idFor('c1', 1))], $this->newsletter->bus->messages(SendCampaign::class));
        $this->campaignHandler()(new SendCampaign('c1'));
        $this->campaignHandler()(new SendCampaign('c1'));

        $queued = $this->newsletter->bus->messages(SendDelivery::class);
        self::assertCount(2, $queued);
        self::assertSame(CampaignStatus::Sent, $this->newsletter->campaigns->get('c1')?->status);
        self::assertSame(['queued' => 2], $this->campaigns()->results('c1')['deliveries']);

        foreach ($queued as $message) {
            $this->deliveryHandler()($message);
        }

        self::assertCount(2, $this->newsletter->mailer->sent);
        self::assertSame(['sent' => 2], $this->campaigns()->results('c1')['deliveries']);
        $email = $this->newsletter->mailer->sent[0];
        $recipient = $email->getTo()[0]->getAddress();
        $subscriber = 'ada@example.com' === $recipient ? $ada : $bob;
        self::assertSame('Hello', $email->getSubject());
        self::assertSame('news@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Example', $email->getFrom()[0]->getName());
        $headers = $email->getHeaders();
        self::assertMatchesRegularExpression('#^<https://example\.com/newsletter/unsubscribe/[A-Za-z0-9_.-]+>$#', (string) $headers->get('List-Unsubscribe')?->getBodyAsString());
        self::assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')?->getBodyAsString());
        self::assertSame('The news <news.default.example.com>', $headers->get('List-Id')?->getBodyAsString());
        self::assertSame('bulk', $headers->get('Precedence')?->getBodyAsString());
        self::assertFalse($headers->has('X-Transport'));
        $metadata = $headers->get('X-Metadata-'.MessageComposer::METADATA);
        self::assertInstanceOf(MetadataHeader::class, $metadata);
        self::assertSame(Delivery::idFor('c1', $subscriber->id), $metadata->getValue());
        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('A preheader', $html);
        self::assertStringContainsString('<h1', $html);
        self::assertStringContainsString('https://example.com/newsletter/c/', $html);
        self::assertStringNotContainsString('href="https://example.com/news"', $html);
        self::assertStringContainsString('mailto:a@example.com', $html);
        self::assertStringContainsString('https://example.com/newsletter/preferences/', $html);
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('News', $text);
        self::assertStringContainsString('<'.substr((string) $headers->get('List-Unsubscribe')?->getBodyAsString(), 1, -1).'>', $text);
        self::assertMatchesRegularExpression('#Your preferences <https://example\.com/newsletter/preferences/[A-Za-z0-9_.-]+>#', $text);
        $tracking = $headers->get(TrackingHeader::NAME);
        self::assertInstanceOf(TrackingHeader::class, $tracking);
        self::assertSame([false, false], [$tracking->getOpens(), $tracking->getClicks()]);

        $link = $this->newsletter->links->verify($this->token('#/unsubscribe/([A-Za-z0-9_.-]+)>#', (string) $headers->get('List-Unsubscribe')?->getBodyAsString()), LinkPurpose::Unsubscribe);
        self::assertSame([$subscriber->id, $list->id, null], [$link->subject, $link->extra, $link->expiresAt]);
        self::assertSame($this->newsletter->clock->now()->getTimestamp() + 31_536_000, $this->newsletter->links->verify($this->token('#/preferences/([A-Za-z0-9_.-]+)"#', $html), LinkPurpose::Preferences)->expiresAt);
        self::assertSame(['default|example.com', 'default|example.org'], array_values(array_unique([...$this->throttle->domains])));
    }

    public function testACampaignWithoutTrackingKeepsItsLinks(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->subscribe($list, 'ada@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', '[Read](https://example.com/news)', clickTracking: ClickTracking::Off);
        $this->newsletter->campaigns->save($campaign);

        $this->campaignHandler()(new SendCampaign('c1'));
        $this->deliveryHandler()($this->newsletter->bus->messages(SendDelivery::class)[0]);

        self::assertStringContainsString('href="https://example.com/news"', (string) $this->newsletter->mailer->sent[0]->getHtmlBody());
        self::assertSame(CampaignStatus::Sent, $this->newsletter->campaigns->get('c1')?->status);
    }

    public function testACampaignOfAStoredLayoutAndAProfile(): void
    {
        $list = $this->newsletter->list('news', false, profile: 'promo');
        $this->subscribe($list, 'ada@example.com');
        $this->newsletter->templates->save(new Template('house', null, '', '<main>{{ content }}</main><a href="{{ unsubscribe_url }}">{{ list_name|upper }}</a>', $this->newsletter->clock->now()));
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', '**Bold**', template: 'house');
        $this->newsletter->campaigns->save($campaign);

        $this->campaignHandler()(new SendCampaign('c1'));
        $this->deliveryHandler()($this->newsletter->bus->messages(SendDelivery::class)[0]);

        $email = $this->newsletter->mailer->sent[0];
        self::assertStringContainsString('<main><p><strong>Bold</strong></p></main>', (string) $email->getHtmlBody());
        self::assertStringContainsString('THE NEWS', (string) $email->getHtmlBody());
        self::assertSame('promo@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame('promotions', $email->getHeaders()->get('X-Transport')?->getBodyAsString());
        self::assertSame('<https://example.com/u>, <mailto:leave@example.com?subject=unsubscribe>', preg_replace('#<https://[^>]+>#', '<https://example.com/u>', (string) $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString()));
        self::assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());
        self::assertStringStartsWith('bounces+', (string) $email->getReturnPath()?->getAddress());
    }

    public function testAMessageIsSkippedForWhoeverMayNotReceiveIt(): void
    {
        $list = $this->newsletter->list('news', false);
        $ada = $this->subscribe($list, 'ada@example.com');
        $bob = $this->subscribe($list, 'bob@example.com');
        $carl = $this->subscribe($list, 'carl@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', 'Hi');
        $this->newsletter->campaigns->save($campaign);
        $this->campaignHandler()(new SendCampaign('c1'));
        $this->newsletter->subscriptions->leave($ada->id, $list->id);
        $this->newsletter->subscriptions->pause($bob->id, $this->newsletter->clock->now()->modify('+1 week'));
        $this->newsletter->suppressions->suppress(Site::default(), 'carl@example.com', 'bounced');
        $this->newsletter->deliveries->create(new Delivery('gone', 'nobody', null, 'x', $this->newsletter->clock->now()));
        $this->newsletter->deliveries->create(new Delivery('no-template', $carl->id, null, null, $this->newsletter->clock->now()));

        foreach ([...$this->newsletter->bus->messages(SendDelivery::class), new SendDelivery('gone'), new SendDelivery('missing')] as $message) {
            $this->deliveryHandler()($message);
        }

        self::assertSame([], $this->newsletter->mailer->sent);
        self::assertSame(['skipped' => 3], $this->campaigns()->results('c1')['deliveries']);
        self::assertSame(DeliveryStatus::Skipped, $this->newsletter->deliveries->get('gone')?->status);
    }

    public function testATemplateSentOnItsOwn(): void
    {
        $list = $this->newsletter->list('news', false);
        $ada = $this->subscribe($list, 'ada@example.com');
        $this->newsletter->templates->save(new Template('welcome', null, 'Welcome to {{ site }} & co', '<p>Hello from {{ site }}</p>', $this->newsletter->clock->now()));
        $this->newsletter->deliveries->create(new Delivery('d1', $ada->id, null, 'welcome', $this->newsletter->clock->now()));
        $this->newsletter->deliveries->create(new Delivery('d2', $ada->id, null, 'missing', $this->newsletter->clock->now()));

        $this->deliveryHandler()(new SendDelivery('d1'));
        $this->deliveryHandler()(new SendDelivery('d2'));

        $email = $this->newsletter->mailer->sent[0];
        self::assertCount(1, $this->newsletter->mailer->sent);
        self::assertSame('Welcome to default & co', $email->getSubject());
        self::assertSame('<p>Hello from default</p>', $email->getHtmlBody());
        self::assertFalse($email->getHeaders()->has('Precedence'));
        self::assertFalse($email->getHeaders()->has('List-Id'));
        self::assertTrue($email->getHeaders()->has('List-Unsubscribe'));
        self::assertSame(DeliveryStatus::Skipped, $this->newsletter->deliveries->get('d2')?->status);
    }

    public function testOutsideTheWindowOrPastThePaceTheMessageWaits(): void
    {
        $list = $this->newsletter->list('news', false, profile: 'office');
        $this->subscribe($list, 'ada@example.com');
        $this->newsletter->campaigns->save(new Campaign('c1', Site::default(), $list->id, 'Hello', 'Hi'));
        $this->campaignHandler()(new SendCampaign('c1'));
        [$message] = $this->newsletter->bus->messages(SendDelivery::class);
        $this->newsletter->clock->modify('2026-11-14 10:00:00 UTC');

        $this->deliveryHandler()($message);
        self::assertSame(169_200_000, $this->lastDelay());
        self::assertSame([], $this->throttle->domains);

        $this->newsletter->clock->modify('2026-11-16 09:00:00 UTC');
        $this->throttle->waits = [42];
        $this->deliveryHandler()($message);
        self::assertSame(42_000, $this->lastDelay());
        self::assertSame([], $this->newsletter->mailer->sent);
        self::assertSame(['queued' => 1], $this->campaigns()->results('c1')['deliveries']);

        $this->deliveryHandler()($message);
        $mailer = $this->newsletter->mailer;
        self::assertSame(['office@example.com'], array_map(static fn (Email $email): string => $email->getFrom()[0]->getAddress(), $mailer->sent));
    }

    public function testATransportFailureIsRetried(): void
    {
        $list = $this->newsletter->list('news', false);
        $ada = $this->subscribe($list, 'ada@example.com');
        $this->newsletter->templates->save(new Template('welcome', null, 'Hi', 'Hi', $this->newsletter->clock->now()));
        $this->newsletter->deliveries->create(new Delivery('d1', $ada->id, null, 'welcome', $this->newsletter->clock->now()));
        $this->newsletter->mailer->failures = 1;

        try {
            $this->deliveryHandler()(new SendDelivery('d1'));
            self::fail('Expected a transport failure.');
        } catch (TransportExceptionInterface) {
            self::assertSame([DeliveryStatus::Failed, 1], [$this->newsletter->deliveries->get('d1')?->status, $this->newsletter->deliveries->get('d1')?->attempts]);
        }

        $this->deliveryHandler()(new SendDelivery('d1'));
        self::assertSame([DeliveryStatus::Sent, 2], [$this->newsletter->deliveries->get('d1')?->status, $this->newsletter->deliveries->get('d1')?->attempts]);
        $this->deliveryHandler()(new SendDelivery('d1'));
        self::assertCount(1, $this->newsletter->mailer->sent);
    }

    public function testAPauseOverResumesAndADeliveryResetsSoftBounces(): void
    {
        $list = $this->newsletter->list('news', false);
        $ada = $this->subscribe($list, 'ada@example.com');
        $this->newsletter->subscriptions->pause($ada->id, $this->newsletter->clock->now()->modify('+1 day'));
        $stored = $this->newsletter->subscribers->get($ada->id);
        self::assertInstanceOf(Subscriber::class, $stored);
        $stored->softBounce(5);
        $this->newsletter->subscribers->save($stored);
        $this->newsletter->templates->save(new Template('welcome', null, 'Hi', 'Hi', $this->newsletter->clock->now()));
        $this->newsletter->deliveries->create(new Delivery('d1', $ada->id, null, 'welcome', $this->newsletter->clock->now()));
        $this->newsletter->clock->modify('+1 day');

        $this->deliveryHandler()(new SendDelivery('d1'));

        $stored = $this->newsletter->subscribers->get($ada->id);
        self::assertSame([SubscriberStatus::Active, 0], [$stored?->status, $stored?->softBounces]);
    }

    public function testACancelledCampaignSendsNothing(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->subscribe($list, 'ada@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', 'Hi');
        $this->newsletter->campaigns->save($campaign);
        $this->campaignHandler()(new SendCampaign('c1'));
        $sending = $this->newsletter->campaigns->get('c1');
        self::assertInstanceOf(Campaign::class, $sending);
        $this->newsletter->campaigns->save(Campaign::restore($sending, CampaignStatus::Cancelled, null, null));

        $this->deliveryHandler()($this->newsletter->bus->messages(SendDelivery::class)[0]);
        self::assertSame([], $this->newsletter->mailer->sent);

        $cancelled = new Campaign('c2', Site::default(), $list->id, 'Hello', 'Hi');
        $this->campaigns()->cancel($cancelled);
        $this->campaignHandler()(new SendCampaign('c2'));
        $this->campaignHandler()(new SendCampaign('none'));
        self::assertSame(CampaignStatus::Cancelled, $this->newsletter->campaigns->get('c2')?->status);
        self::assertCount(1, $this->newsletter->bus->messages(SendDelivery::class));
    }

    public function testScheduledCampaignsStartAtTheirTime(): void
    {
        $list = $this->newsletter->list('news', false);
        $early = new Campaign('early', Site::default(), $list->id, 'A', 'A');
        $late = new Campaign('late', Site::default(), $list->id, 'B', 'B');
        $this->campaigns()->schedule($early, $this->newsletter->clock->now()->modify('+1 hour'));
        $this->campaigns()->schedule($late, $this->newsletter->clock->now()->modify('+2 hours'));
        $handler = new DispatchDueCampaignsHandler($this->campaigns());

        $handler(new DispatchDueCampaigns());
        self::assertSame([], $this->newsletter->bus->messages(SendCampaign::class));
        $this->newsletter->clock->modify('+1 hour');
        $handler(new DispatchDueCampaigns());
        self::assertEquals([new SendCampaign('early', SendRun::idFor('early', 1))], $this->newsletter->bus->messages(SendCampaign::class));
        self::assertSame(CampaignStatus::Sending, $this->newsletter->campaigns->get('early')?->status);
        self::assertSame(0, $this->campaigns()->startDue());
        self::assertSame(CampaignStatus::Scheduled, $this->campaigns()->get('late')->status);

        $this->expectException(NotFound::class);
        $this->campaigns()->get('none');
    }

    public function testRecipientsAreReadInPagesAndASegmentNeedsAResolver(): void
    {
        $list = $this->newsletter->list('news', false);

        for ($i = 0; $i < 502; ++$i) {
            $this->newsletter->subscriptions->subscribe($list, \sprintf('reader%03d@example.com', $i));
        }

        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', 'Hi');
        $ids = iterator_to_array(new ListRecipients($this->newsletter->subscribers)->recipients($campaign, $this->newsletter->clock->now()), false);
        self::assertCount(502, $ids);
        self::assertCount(502, array_unique($ids));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The campaign names the segment "vip", and no segment resolver is configured.');
        iterator_to_array(new ListRecipients($this->newsletter->subscribers)->recipients(new Campaign('c2', Site::default(), $list->id, 'Hello', 'Hi', segment: 'vip'), $this->newsletter->clock->now()));
    }

    public function testATransportFailureFailsTheConfirmationForMessengerToRetry(): void
    {
        $list = $this->newsletter->list();
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $ada = $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com');
        self::assertInstanceOf(Subscriber::class, $ada);
        $mailer = $this->newsletter->mailer;
        $mailer->failures = 1;

        try {
            $this->confirmationHandler()(new SendConfirmation($ada->id, $list->id));
            self::fail('Expected a transport failure.');
        } catch (TransportExceptionInterface $transportException) {
            self::assertSame('The transport is down.', $transportException->getMessage());
        }

        self::assertCount(0, $this->newsletter->mailer->sent);
        $this->confirmationHandler()(new SendConfirmation($ada->id, $list->id));
        self::assertSame(['ada@example.com'], array_map(static fn (Email $email): string => $email->getTo()[0]->getAddress(), $mailer->sent));
    }

    public function testTheConfirmationOfADoubleOptIn(): void
    {
        $list = $this->newsletter->list();
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $ada = $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com');
        self::assertInstanceOf(Subscriber::class, $ada);

        $this->confirmationHandler()(new SendConfirmation($ada->id, $list->id));
        $email = $this->newsletter->mailer->sent[0];
        self::assertSame('Confirm your subscription to The news', $email->getSubject());
        self::assertFalse($email->getHeaders()->has('List-Unsubscribe'));
        self::assertFalse($email->getHeaders()->has('Precedence'));
        $link = $this->newsletter->links->verify($this->token('#/confirm/([A-Za-z0-9_.-]+)"#', (string) $email->getHtmlBody()), LinkPurpose::Confirm);
        self::assertSame([$ada->id, $list->id, $this->newsletter->clock->now()->getTimestamp() + 604_800], [$link->subject, $link->extra, $link->expiresAt]);
        self::assertMatchesRegularExpression('#<https://example\.com/newsletter/confirm/[A-Za-z0-9_.-]+>#', (string) $email->getTextBody());
        self::assertSame('opens=false; clicks=false', $email->getHeaders()->get(TrackingHeader::NAME)?->getBodyAsString());

        $this->newsletter->templates->save(new Template('confirmation', Site::default(), 'Please confirm {{ list_name }}', '<a href="{{ confirm_url }}">Yes</a>', $this->newsletter->clock->now()));
        $this->confirmationHandler()(new SendConfirmation($ada->id, $list->id));
        self::assertSame('Please confirm The news', $this->newsletter->mailer->sent[1]->getSubject());
        self::assertMatchesRegularExpression('#^Yes <https://example\.com/newsletter/confirm/[A-Za-z0-9_.-]+>$#', (string) $this->newsletter->mailer->sent[1]->getTextBody());

        $this->newsletter->subscriptions->confirm($ada->id, $list->id);
        $this->confirmationHandler()(new SendConfirmation($ada->id, $list->id));
        $this->confirmationHandler()(new SendConfirmation('nobody', $list->id));
        self::assertCount(2, $this->newsletter->mailer->sent);
    }

    public function testADomainThatReceivesNoMailRefusesTheSubscriptionInTheWorker(): void
    {
        $list = $this->newsletter->list();
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };
        $resolver = new StaticResolver(['dead-domain.com' => MailDomain::NoRecord, 'null-mx.org' => MailDomain::NullMx, 'xn--bcher-kva.ca' => MailDomain::NoRecord, 'live.net' => MailDomain::Mx]);
        $handler = $this->confirmationHandler($resolver, $logger);

        foreach (['ada@dead-domain.com', 'bob@null-mx.org', 'cy@bücher.ca'] as $address) {
            self::assertSame(SubscribeOutcome::ConfirmationSent, $this->newsletter->subscriptions->subscribe($list, $address));
            $subscriber = $this->newsletter->subscribers->byEmail(Site::default(), $address);
            self::assertInstanceOf(Subscriber::class, $subscriber);
            $this->newsletter->clock->modify('+1 minute');
            $handler(new SendConfirmation($subscriber->id, $list->id));
            $membership = $this->newsletter->subscribers->membership($subscriber->id, $list->id);
            self::assertInstanceOf(Membership::class, $membership);
            self::assertSame(MembershipStatus::Refused, $membership->status);
            self::assertSame($this->newsletter->clock->now()->getTimestamp(), $membership->changedAt->getTimestamp());
        }

        self::assertSame([], $this->newsletter->mailer->sent);
        self::assertCount(3, $logger->records);
        [$level, $message, $context] = $logger->records[1];
        self::assertSame('notice', $level);
        self::assertStringNotContainsString('@', $message.json_encode($context, \JSON_THROW_ON_ERROR));
        self::assertSame(['subscriber', 'list', 'mail_domain'], array_keys($context));
        self::assertSame([$list->id, 'null_mx'], [$context['list'], $context['mail_domain']]);
        self::assertSame('The confirmation of subscriber {subscriber} to list {list} was not sent: the domain of the address receives no mail ({mail_domain}).', $message);

        foreach (['dee@live.net', 'eve@unknown.org', 'fay@[192.0.2.1]'] as $address) {
            $subscriber = new Subscriber('s-'.substr($address, 0, 3), Site::default(), $address, null, SubscriberStatus::Pending, $this->newsletter->clock->now());
            $this->newsletter->subscribers->save($subscriber);
            $this->newsletter->subscribers->saveMembership(new Membership($subscriber->id, $list->id, MembershipStatus::Pending, $this->newsletter->clock->now()));
            $handler(new SendConfirmation($subscriber->id, $list->id));
        }

        self::assertSame(['dee@live.net', 'eve@unknown.org', 'fay@[192.0.2.1]'], $this->recipients());
        self::assertCount(3, $logger->records);
    }

    public function testAnAddressTheDefaultPolicyDoesNotReadIsSentItsConfirmation(): void
    {
        $list = $this->newsletter->list();
        $subscriber = new Subscriber('s-odd', Site::default(), '"a b"@example.com', null, SubscriberStatus::Pending, $this->newsletter->clock->now());
        $this->newsletter->subscribers->save($subscriber);
        $this->newsletter->subscribers->saveMembership(new Membership($subscriber->id, $list->id, MembershipStatus::Pending, $this->newsletter->clock->now()));

        $this->confirmationHandler(new StaticResolver(otherwise: MailDomain::NoRecord))(new SendConfirmation($subscriber->id, $list->id));

        self::assertCount(1, $this->newsletter->mailer->sent);
    }

    public function testProfiles(): void
    {
        $profile = new SenderProfile('promo', 'a@example.com', rate: 30, domainRates: ['gmail.com' => 10]);
        self::assertSame(10, $profile->rateFor('Gmail.com'));
        self::assertSame(30, $profile->rateFor('example.com'));
        self::assertTrue($profile->window->contains($this->newsletter->clock->now()));
        $profiles = new SenderProfiles([$profile, new SenderProfile('default', 'b@example.com')], 'default');
        self::assertSame('b@example.com', $profiles->get(null)->from);
        self::assertSame($profile, $profiles->get('promo'));
        self::assertSame(['promo', 'default'], $profiles->names());

        foreach ([
            '"Promo" is not a sender profile name.' => static fn (): SenderProfile => new SenderProfile('Promo', 'a@example.com'),
            'The rates and the daily ceiling of the sender profile "p" are positive.' => static fn (): SenderProfile => new SenderProfile('p', 'a@example.com', rate: 0),
            'The default sender profile "x" is not configured.' => static fn (): SenderProfiles => new SenderProfiles([$profile], 'x'),
        ] as $message => $build) {
            try {
                $build();
                self::fail('Expected a refusal.');
            } catch (InvalidNewsletterValue $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        $refused = 0;

        foreach ([['daily' => 0], ['domains' => ['a.com' => 0]]] as $bad) {
            try {
                new SenderProfile('p', 'a@example.com', dailyCap: $bad['daily'] ?? null, domainRates: $bad['domains'] ?? []);
            } catch (InvalidNewsletterValue) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);

        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('No sender profile "none".');
        $profiles->get('none');
    }

    public function testUrlsWithoutABaseAddressTakeTheRouter(): void
    {
        self::assertStringStartsWith('http://fallback.example/newsletter/c/', $this->newsletter->urls()->to(NewsletterUrls::CLICK, 'tok.en', Site::of('shop')));
        self::assertSame('https://example.com/newsletter/c/tok.en', $this->newsletter->urls()->to(NewsletterUrls::CLICK, 'tok.en', Site::default()));
    }

    private function subscribe(MailingList $list, string $email): Subscriber
    {
        $this->newsletter->subscriptions->subscribe($list, $email);
        $subscriber = $this->newsletter->subscribers->byEmail($list->site, $email);
        self::assertInstanceOf(Subscriber::class, $subscriber);

        return $subscriber;
    }

    private function token(string $pattern, string $subject): string
    {
        self::assertSame(1, preg_match($pattern, $subject, $match));

        return $match[1] ?? '';
    }

    private function lastDelay(): ?int
    {
        $last = end($this->newsletter->bus->envelopes);

        return false === $last ? null : $last->last(DelayStamp::class)?->getDelay();
    }

    private function campaigns(): Campaigns
    {
        return new Campaigns($this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->bus, $this->newsletter->clock, $this->newsletter->sendRuns(), new ApprovalPolicy(required: false));
    }

    private function campaignHandler(): SendCampaignHandler
    {
        return new SendCampaignHandler($this->newsletter->campaigns, $this->newsletter->deliveries, new ListRecipients($this->newsletter->subscribers), $this->newsletter->bus, $this->newsletter->clock, $this->newsletter->sendRuns(), false);
    }

    private function profiles(): SenderProfiles
    {
        return new SenderProfiles([
            new SenderProfile('default', 'news@example.com', 'Example'),
            new SenderProfile('promo', 'promo@example.com', '', 'reply@example.com', 'promotions', unsubscribeMailbox: 'leave@example.com'),
            new SenderProfile('office', 'office@example.com', window: SendWindow::of([1, 2, 3, 4, 5], '09:00', '17:00')),
        ], 'default');
    }

    private function composer(): MessageComposer
    {
        return new MessageComposer(new Verp('bounces', 'example.com'), 'a-verp-key-of-sixteen-bytes');
    }

    private function deliveryHandler(): SendDeliveryHandler
    {
        return new SendDeliveryHandler(
            $this->newsletter->deliveries,
            $this->newsletter->subscribers,
            $this->newsletter->campaigns,
            $this->newsletter->lists,
            $this->newsletter->templates,
            $this->newsletter->suppressions,
            $this->profiles(),
            $this->throttle,
            $this->newsletter->renderer(),
            $this->composer(),
            new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock),
            $this->newsletter->links,
            $this->newsletter->urls(),
            $this->newsletter->mailer,
            $this->newsletter->bus,
            $this->newsletter->clock,
        );
    }

    /**
     * @return list<string> the first recipient of each message sent, in order
     */
    private function recipients(): array
    {
        $recipients = [];

        foreach ($this->newsletter->mailer->sent as $email) {
            $recipients[] = $email->getTo()[0]->getAddress();
        }

        return $recipients;
    }

    private function confirmationHandler(?StaticResolver $resolver = null, ?AbstractLogger $logger = null): SendConfirmationHandler
    {
        return new SendConfirmationHandler(
            $this->newsletter->subscribers,
            $this->newsletter->lists,
            $this->newsletter->templates,
            $this->profiles(),
            $this->newsletter->links,
            $this->newsletter->urls(),
            $this->newsletter->renderer(),
            $this->composer(),
            $this->newsletter->twig(),
            $this->newsletter->mailer,
            $this->newsletter->clock,
            $resolver,
            $logger,
        );
    }
}
