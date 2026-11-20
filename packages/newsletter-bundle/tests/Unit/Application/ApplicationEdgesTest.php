<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Contract\ReportAuthenticator;
use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\Mime\Part;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;
use Phil\BounceParser\Verp;
use Phil\NewsletterBundle\Application\ApprovalPolicy;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Bounces\BounceOutcome;
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\LinkRewriter;
use Phil\NewsletterBundle\Application\Content\RangeRefusal;
use Phil\NewsletterBundle\Application\Content\RenderedMessage;
use Phil\NewsletterBundle\Application\Content\SandboxedTemplates;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered;
use Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler;
use Phil\NewsletterBundle\Application\Handler\SendCampaignHandler;
use Phil\NewsletterBundle\Application\Handler\SendConfirmationHandler;
use Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler;
use Phil\NewsletterBundle\Application\ListRecipients;
use Phil\NewsletterBundle\Application\Message\FetchBounces;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Application\Message\SendConfirmation;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfile;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\NewsletterBundle\Tests\Unit\Fixture\ScriptedMailbox;
use Phil\NewsletterBundle\Tests\Unit\Fixture\ScriptedThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(BounceHandler::class)]
#[CoversClass(Campaigns::class)]
#[CoversClass(ContentRenderer::class)]
#[CoversClass(LinkRewriter::class)]
#[CoversClass(RangeRefusal::class)]
#[CoversClass(SandboxedTemplates::class)]
#[CoversClass(AudienceChanged::class)]
#[CoversClass(WebhookNotDelivered::class)]
#[CoversClass(FetchBouncesHandler::class)]
#[CoversClass(SendCampaignHandler::class)]
#[CoversClass(SendConfirmationHandler::class)]
#[CoversClass(SendDeliveryHandler::class)]
#[CoversClass(MessageComposer::class)]
#[CoversClass(SenderProfile::class)]
#[CoversClass(SignedLinks::class)]
#[CoversClass(Subscriptions::class)]
#[CoversClass(ClickTracker::class)]
#[CoversClass(ListRecipients::class)]
final class ApplicationEdgesTest extends TestCase
{
    private const string VERP_KEY = 'a-verp-key-of-sixteen-bytes';

    private Newsletter $newsletter;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
    }

    // Bounces

    public function testARuleThatSuppressesABounceIsAHardBounceOrAComplaint(): void
    {
        $this->subscriber('ada@example.org');
        $this->subscriber('bob@example.org');
        $hard = new BounceParser(new Rules(Rule::containing('blocked', Field::Diagnostic, 'No such user', BounceKind::Hard, BounceAction::Suppress, RuleScope::Always)));
        $complaint = new BounceParser(new Rules(Rule::containing('spam', Field::Diagnostic, 'No such user', BounceKind::Complaint, BounceAction::Suppress, RuleScope::Always)));

        self::assertSame([BounceOutcome::Bounced], $this->bounces($hard)->handleMessage($this->dsn('ada@example.org')));
        self::assertSame([BounceOutcome::Complained], $this->bounces($complaint)->handleMessage($this->dsn('bob@example.org')));
        self::assertSame(SubscriberStatus::Bounced, $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.org')?->status);
        self::assertSame(SubscriberStatus::Complained, $this->newsletter->subscribers->byEmail(Site::default(), 'bob@example.org')?->status);
    }

    public function testAnAuthenticComplaintUnsubscribesTheAddress(): void
    {
        $this->subscriber('grace@example.com');
        $parser = new BounceParser(authenticator: new class implements ReportAuthenticator {
            #[Override]
            public function isAuthentic(Part $message, FeedbackReport $report): bool
            {
                return true;
            }
        });
        $arf = implode("\r\n", [
            'From: <feedback@fbl.example.com>',
            'To: <abuse@example.net>',
            'Subject: FW: November news',
            'MIME-Version: 1.0',
            'Content-Type: multipart/report; report-type=feedback-report; boundary="b"',
            '',
            '--b',
            'Content-Type: text/plain',
            '',
            'An abuse report.',
            '--b',
            'Content-Type: message/feedback-report',
            '',
            'Feedback-Type: abuse',
            'Version: 1',
            'Original-Rcpt-To: <Grace@Example.COM>',
            '',
            '--b--',
            '',
        ]);

        self::assertSame([BounceOutcome::Unsubscribed], $this->bounces($parser)->handleMessage($arf));
        self::assertSame(SubscriberStatus::Unsubscribed, $this->newsletter->subscribers->byEmail(Site::default(), 'grace@example.com')?->status);
    }

    public function testAMessageThatNamesNoAddressIsIgnoredEvenWhenARuleDecidesIt(): void
    {
        $parser = new BounceParser(new Rules(Rule::containing('blocked', Field::Body, 'blocked by policy', BounceKind::Hard)));

        self::assertSame([BounceOutcome::Ignored], $this->bounces($parser)->handleMessage("From: x@example.net\r\nSubject: Notice\r\n\r\nThe message was blocked by policy."));
    }

    public function testTheMailboxIsReadForAtLeastOneMessageAndEachMessageIsHandled(): void
    {
        $this->subscriber('ada@example.org');
        $mailbox = new ScriptedMailbox(['1' => $this->dsn('ada@example.org')]);

        self::assertSame(1, new FetchBouncesHandler($mailbox, $this->bounces(new BounceParser()))(new FetchBounces(-3)));
        self::assertSame(1, $mailbox->limit);
        self::assertSame(SubscriberStatus::Bounced, $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.org')?->status);

        $mailbox = new ScriptedMailbox([]);
        new FetchBouncesHandler($mailbox, $this->bounces(new BounceParser()))(new FetchBounces(0));
        self::assertSame(1, $mailbox->limit);
        new FetchBouncesHandler($mailbox, $this->bounces(new BounceParser()))(new FetchBounces(1));
        self::assertSame(1, $mailbox->limit);
        new FetchBouncesHandler($mailbox, $this->bounces(new BounceParser()))(new FetchBounces(2));
        self::assertSame(2, $mailbox->limit);
    }

    // Campaigns and sending

    public function testTheResultsOfACampaignAreItsDeliveriesAndItsClicks(): void
    {
        $this->newsletter->deliveries->create(new Delivery('d1', 's1', 'c1', null, $this->newsletter->clock->now()));
        $this->newsletter->engagement->countClick('c1', 3);

        self::assertSame(['deliveries' => ['queued' => 1], 'clicks' => [3 => 1]], $this->campaigns()->results('c1'));
    }

    public function testACampaignStartedStaysStartedWhenTheSendingStops(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->subscribe($list, 'ada@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', 'Body');
        $this->newsletter->campaigns->save($campaign);
        $bus = new class implements MessageBusInterface {
            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new RuntimeException('The queue is down.');
            }
        };
        $handler = new SendCampaignHandler($this->newsletter->campaigns, $this->newsletter->deliveries, new ListRecipients($this->newsletter->subscribers), $bus, $this->newsletter->clock, $this->newsletter->sendRuns(), false);

        try {
            $handler(new SendCampaign('c1'));
            self::fail('The queue is down.');
        } catch (RuntimeException) {
            self::assertSame(CampaignStatus::Sending, $this->newsletter->campaigns->get('c1')?->status);
        }
    }

    public function testAConfirmationIsSentOnlyForAPendingMembershipOfAnExistingSubscriberAndList(): void
    {
        $list = $this->newsletter->list('news', true);
        $other = $this->newsletter->list('other', true);
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $ada = $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com');
        self::assertInstanceOf(Subscriber::class, $ada);
        $handler = $this->confirmationHandler();

        $handler(new SendConfirmation($ada->id, 'no-such-list'));
        $handler(new SendConfirmation($ada->id, $other->id));
        $this->newsletter->subscribers->saveMembership(new Membership($ada->id, 'ghost', MembershipStatus::Pending, $this->newsletter->clock->now()));
        $handler(new SendConfirmation($ada->id, 'ghost'));
        $handler(new SendConfirmation('nobody', 'no-such-list'));
        self::assertSame([], $this->recipients());

        $handler(new SendConfirmation($ada->id, $list->id));
        self::assertSame([$ada->email], $this->recipients());
    }

    public function testAConfirmationIsSentFromTheTemplateOfTheBundleWithItsWhitespaceTrimmed(): void
    {
        $list = $this->newsletter->list('news', true);
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $ada = $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com');
        self::assertInstanceOf(Subscriber::class, $ada);
        $twig = new Environment(new ArrayLoader([SendConfirmationHandler::BUILT_IN => "{% block subject %}\n  Hello {{ list_name }} on {{ site }}\n{% endblock %}{% block html %}<p>{{ confirm_url }}</p>{% endblock %}"]));

        $this->confirmationHandler($twig)(new SendConfirmation($ada->id, $list->id));

        $email = $this->newsletter->mailer->sent[0];
        self::assertSame('Hello The news on default', $email->getSubject());
        $metadata = $email->getHeaders()->get('X-Metadata-'.MessageComposer::METADATA);
        self::assertSame('confirm-'.substr(hash('sha256', $ada->id.$list->id), 0, 24), $metadata?->getBodyAsString());
        self::assertNotSame('', (string) $email->getTextBody());
    }

    public function testACampaignKeepsItsOwnSenderProfileOverTheOneOfItsList(): void
    {
        $list = $this->newsletter->list('news', false, profile: 'default');
        $this->subscribe($list, 'ada@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', 'Body', senderProfile: 'promo');
        $this->newsletter->campaigns->save($campaign);
        $this->newsletter->deliveries->create(new Delivery(Delivery::idFor('c1', $this->subscriberId('ada@example.com')), $this->subscriberId('ada@example.com'), 'c1', null, $this->newsletter->clock->now()));

        $this->deliveryHandler()(new SendDelivery(Delivery::idFor('c1', $this->subscriberId('ada@example.com'))));

        self::assertSame('promo@example.com', $this->newsletter->mailer->sent[0]->getFrom()[0]->getAddress());
    }

    public function testAMessageThatWaitsOneSecondIsDelayedOneSecond(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->subscribe($list, 'ada@example.com');
        $campaign = new Campaign('c1', Site::default(), $list->id, 'Hello', 'Body');
        $this->newsletter->campaigns->save($campaign);
        $id = Delivery::idFor('c1', $this->subscriberId('ada@example.com'));
        $this->newsletter->deliveries->create(new Delivery($id, $this->subscriberId('ada@example.com'), 'c1', null, $this->newsletter->clock->now()));

        $this->deliveryHandler(new ScriptedThrottle([1]))(new SendDelivery($id));

        $last = end($this->newsletter->bus->envelopes);
        self::assertInstanceOf(Envelope::class, $last);
        self::assertSame(1000, $last->last(DelayStamp::class)?->getDelay());
    }

    public function testAMessageIsComposedAsBulkUnlessItIsNot(): void
    {
        $subscriber = new Subscriber('s1', Site::default(), 'ada@example.com', null, SubscriberStatus::Active, $this->newsletter->clock->now());
        $profile = new SenderProfile('default', 'news@example.com');
        $message = new RenderedMessage('S', '<p>H</p>', 'H');
        $composer = new MessageComposer();

        self::assertSame('bulk', $composer->compose('d1', $subscriber, $profile, $message, '')->getHeaders()->get('Precedence')?->getBodyAsString());
        self::assertFalse($composer->compose('d1', $subscriber, $profile, $message, '', null, false)->getHeaders()->has('Precedence'));
    }

    public function testASenderProfileCountsFromOneAndTakesItsDefaults(): void
    {
        self::assertSame(120, new SenderProfile('default', 'a@example.com')->rate);
        self::assertSame(1, new SenderProfile('default', 'a@example.com', rate: 1)->rate);
        self::assertSame(1, new SenderProfile('default', 'a@example.com', dailyCap: 1)->dailyCap);
        self::assertSame(['gmail.com' => 1], new SenderProfile('default', 'a@example.com', domainRates: ['gmail.com' => 1])->domainRates);
    }

    #[DataProvider('profileNames')]
    public function testASenderProfileNameIsAnchoredAtBothEnds(string $name): void
    {
        $this->expectException(InvalidNewsletterValue::class);

        new SenderProfile($name, 'a@example.com');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function profileNames(): iterable
    {
        yield 'a suffix' => ['ab!'];
        yield 'a trailing new line' => ["ab\n"];
        yield 'a prefix' => ['!ab'];
    }

    // Content

    public function testASubjectAndATextKeepTheirQuotes(): void
    {
        $renderer = $this->newsletter->renderer();
        $template = new Template('welcome', null, 'Hi {{ name }}', '<p>{{ name }} / {{ subject }} / {{ list_name }}</p>', $this->newsletter->clock->now());

        $rendered = $renderer->template($template, ['name' => "O'Neil", 'list_name' => 'The news']);

        self::assertSame("Hi O'Neil", $rendered->subject);
        self::assertSame('<p>O&#039;Neil / Hi O&#039;Neil / The news</p>', $rendered->html);
        self::assertSame('"quoted" it\'s', $renderer->text('<p>&quot;quoted&quot; it&#039;s</p>'));
        self::assertSame("one\n\ntwo", $renderer->text("<p>one</p>\n\n\n\n<p>two</p>\n\n"));
    }

    public function testLinksAreRewrittenInTheBodyAsABrowserReadsIt(): void
    {
        $rewriter = new LinkRewriter();
        $track = static fn (string $url): string => 'https://t.example/?u='.rawurlencode($url);

        self::assertSame('<p>one<br>two', $rewriter->rewrite('<p>one<br>two', $track));
        self::assertSame("\n<p><a href=\"https://t.example/?u=https%3A%2F%2Fx.test%2Fa\">A</a></p>", $rewriter->rewrite("\n<p><a href=\"https://x.test/a\">A</a></p>", $track));
        self::assertSame('<a href="https://t.example/?u=https%3A%2F%2Fx.test%2Fa">A</a>', $rewriter->rewrite('<a href=" https://x.test/a ">A</a>', $track));
    }

    public function testTheRangeRefusalRunsFirst(): void
    {
        self::assertSame(0, new RangeRefusal()->getPriority());
    }

    public function testASandboxKeepsItsCompiledTemplatesWhenOneIsRenderedAgain(): void
    {
        $sandbox = new SandboxedTemplates();

        for ($i = 0; $i < 64; ++$i) {
            $sandbox->render('{{ x }} '.$i, ['x' => 'a']);
        }

        $compiled = new ReflectionProperty(SandboxedTemplates::class, 'compiled');
        self::assertSame(64, $this->arraySize($compiled->getValue($sandbox)));

        $sandbox->render('{{ x }} 0', ['x' => 'a']);
        self::assertSame(64, $this->arraySize($compiled->getValue($sandbox)));

        $sandbox->render('{{ x }} new', ['x' => 'a']);
        self::assertSame(1, $this->arraySize($compiled->getValue($sandbox)));
    }

    public function testASandboxKeepsItsEnvironmentBetweenTemplatesAndReadsAMissingVariableAsEmpty(): void
    {
        $sandbox = new SandboxedTemplates();
        $property = new ReflectionProperty(SandboxedTemplates::class, 'sandbox');

        self::assertSame('[]', $sandbox->render('[{{ missing }}]', []));
        $first = $property->getValue($sandbox);
        $sandbox->render('{{ other }}', []);

        self::assertSame($first, $property->getValue($sandbox));
    }

    // Links

    public function testAFieldOfALinkCannotHoldTheSeparatorWhereverItIs(): void
    {
        $links = $this->newsletter->links;

        foreach ([['a', 'b|c'], ['a|b', 'c']] as [$subject, $extra]) {
            try {
                $links->sign(LinkPurpose::Click, $subject, $extra);
                self::fail('A "|" is refused.');
            } catch (LinkRefused $refused) {
                self::assertSame('A field of a link holds no "|".', $refused->getMessage());
            }
        }

        self::assertSame('a:b', $links->verify($links->sign(LinkPurpose::Click, 'a:b', 'c'), LinkPurpose::Click)->subject);
    }

    public function testATokenOfExactlyTheLongestLengthIsReadNotRefusedAsMalformed(): void
    {
        $token = str_repeat('A', 489).'.'.str_repeat('A', 22);
        self::assertSame(512, \strlen($token));

        try {
            $this->newsletter->links->verify($token, LinkPurpose::Click);
            self::fail('The link is not signed.');
        } catch (LinkRefused $linkRefused) {
            self::assertSame('The link is not signed by the newsletter.', $linkRefused->getMessage());
        }
    }

    public function testATokenWithSomethingBeforeItIsMalformed(): void
    {
        $token = $this->newsletter->links->sign(LinkPurpose::Click, 'c1', '0:');

        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains('The link is malformed.');
        $this->newsletter->links->verify('!'.$token, LinkPurpose::Click);
    }

    #[DataProvider('forgedPayloads')]
    public function testASignedPayloadOfTheWrongShapeIsRefused(string $payload): void
    {
        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains('The link is for something else.');

        $this->newsletter->links->verify($this->forge($payload), LinkPurpose::Click);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgedPayloads(): iterable
    {
        yield 'a prefix on the instant' => ['click|c1|0:|x1800000000|'];
        yield 'a suffix on the instant' => ['click|c1|0:|1800000000x|'];
        yield 'a new line on the instant' => ["click|c1|0:|1800000000\n|"];
        yield 'a new line on the expiry' => ["click|c1|0:|1800000000|1800000100\n"];
        yield 'a suffix on the expiry' => ['click|c1|0:|1800000000|1800000100x'];
    }

    // Clicks

    public function testARecipientWithAColonInItsDeliveryIdIsNotCounted(): void
    {
        $campaign = new Campaign('c1', Site::default(), 'l', 'S', 'B', clickTracking: ClickTracking::PerRecipient);
        $this->newsletter->campaigns->save($campaign);
        $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/a');
        $this->newsletter->deliveries->create(new Delivery('d1', 's1', 'c1', null, $this->newsletter->clock->now()));

        $tracker = new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock);
        $token = $this->newsletter->links->sign(LinkPurpose::Click, 'c1', '0:d1:more');
        $this->newsletter->clock->modify('+1 minute');

        self::assertSame('https://example.com/a', $tracker->follow($token, 'GET', []));

        self::assertSame([], $this->newsletter->engagement->recipientClicks('s1'));
        self::assertSame([0 => 1], $this->newsletter->engagement->clicks('c1'));
    }

    public function testTheIndexOfALinkIsAnchoredAtItsEnd(): void
    {
        $campaign = new Campaign('c1', Site::default(), 'l', 'S', 'B');
        $this->newsletter->campaigns->save($campaign);
        $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/a');

        $tracker = new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock);

        $this->expectException(LinkRefused::class);
        $this->expectExceptionMessageIsOrContains('The link is for something else.');
        $tracker->follow($this->newsletter->links->sign(LinkPurpose::Click, 'c1', "0\n:"), 'GET', []);
    }

    public function testAPreviewIsNotACountedClickWhateverItsCase(): void
    {
        $tracker = new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock);
        $issued = $this->newsletter->clock->now()->getTimestamp() - 3600;

        self::assertTrue($tracker->counts('GET', [], $issued));
        self::assertFalse($tracker->counts('GET', ['purpose' => 'Preview'], $issued));
        self::assertFalse($tracker->counts('GET', ['sec-purpose' => 'prefetch;prerender'], $issued));
        self::assertFalse($tracker->counts('GET', ['x-moz' => 'PREFETCH'], $issued));
    }

    // Subscriptions

    public function testAnExternalIdOfASubscriberIsKeptWhenAnotherIsGiven(): void
    {
        $news = $this->newsletter->list('news', false);
        $events = $this->newsletter->list('events', false);

        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com', 'old');
        $this->newsletter->subscriptions->subscribe($events, 'ada@example.com', 'new');

        self::assertSame('old', $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com')?->externalId);

        $this->newsletter->subscriptions->subscribe($this->newsletter->list('third', false), 'bob@example.com');
        $this->newsletter->subscriptions->subscribe($news, 'bob@example.com', 'late');
        self::assertSame('late', $this->newsletter->subscribers->byEmail(Site::default(), 'bob@example.com')?->externalId);
    }

    public function testASingleOptInMakesTheMembershipActive(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $ada = $this->subscriberId('ada@example.com');

        self::assertSame(MembershipStatus::Active, $this->newsletter->subscribers->membership($ada, $list->id)?->status);
    }

    public function testConfirmingAndJoiningAnnounceTheConfirmation(): void
    {
        $news = $this->newsletter->list('news', true);
        $events = $this->newsletter->list('events', false);
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');
        $ada = $this->subscriberId('ada@example.com');
        $before = \count($this->newsletter->events->events);

        self::assertTrue($this->newsletter->subscriptions->confirm($ada, $news->id));
        self::assertTrue($this->newsletter->subscriptions->join($ada, $events));

        $announced = \array_slice($this->newsletter->events->events, $before);
        self::assertSame(['confirmed', 'confirmed'], array_map(static fn (object $event): string => $event instanceof AudienceChanged ? $event->type->value : '', $announced));
        self::assertSame([$news->id, $events->id], array_map(static fn (object $event): ?string => $event instanceof AudienceChanged ? $event->listId : null, $announced));
    }

    public function testLeavingOneListLeavesTheOthers(): void
    {
        $news = $this->newsletter->list('news', false);
        $events = $this->newsletter->list('events', false);
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');
        $this->newsletter->subscriptions->subscribe($events, 'ada@example.com');

        $ada = $this->subscriberId('ada@example.com');

        self::assertTrue($this->newsletter->subscriptions->leave($ada, $events->id));

        self::assertSame(MembershipStatus::Active, $this->newsletter->subscribers->membership($ada, $news->id)?->status);
        self::assertSame(MembershipStatus::Unsubscribed, $this->newsletter->subscribers->membership($ada, $events->id)?->status);
        self::assertSame(SubscriberStatus::Active, $this->newsletter->subscribers->get($ada)?->status);
    }

    public function testASubscriberAndAnAnnouncementAreReachableFromOutside(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $ada = $this->newsletter->subscriptions->subscriber($this->subscriberId('ada@example.com'));
        $before = \count($this->newsletter->events->events);
        $at = new DateTimeImmutable('@1800000000');

        $this->newsletter->subscriptions->announce(AudienceEventType::Paused, $ada, 'l1', $at);

        self::assertEquals([new AudienceChanged(AudienceEventType::Paused, $ada->id, 'default', 'l1', null, $at)], \array_slice($this->newsletter->events->events, $before));
    }

    public function testTheDomainOfAnAddressIsLowercasedWhateverItsLetters(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($list, 'ada@EXAMPLE.com');
        $this->newsletter->subscriptions->subscribe($list, 'bob@ÉCOLE.example.com');

        self::assertSame('ada@example.com', $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com')?->email);
        self::assertSame('bob@école.example.com', $this->newsletter->subscribers->byEmail(Site::default(), 'bob@école.example.com')?->email);
    }

    public function testTheIdOfAnAudienceEventIsDrawnFromEachOfItsParts(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $event = new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', 'l1', null, $at);

        self::assertSame(substr(hash('sha256', "confirmed\0s1\0l1\0001800000000"), 0, 32), $event->id());
        self::assertSame(substr(hash('sha256', "erased\0s1\0\0001800000000"), 0, 32), new AudienceChanged(AudienceEventType::Erased, 's1', 'default', null, null, $at)->id());
        self::assertNotSame($event->id(), new AudienceChanged(AudienceEventType::Confirmed, 's1', 'default', 'l2', null, $at)->id());
    }

    public function testAnUndeliveredWebhookHasNoErrorCode(): void
    {
        self::assertSame(0, new WebhookNotDelivered('No.', false)->getCode());
        self::assertSame(0, new WebhookNotDelivered('No.', true)->getCode());
    }

    // Helpers

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        return array_map(static fn (Email $email): string => $email->getTo()[0]->getAddress(), $this->newsletter->mailer->sent);
    }

    private function arraySize(mixed $value): int
    {
        self::assertIsArray($value);

        return \count($value);
    }

    private function dsn(string $recipient): string
    {
        return implode("\r\n", ['From: MAILER-DAEMON@mx.example.org', 'To: bounces@example.com', 'Subject: Undelivered', 'MIME-Version: 1.0', 'Content-Type: multipart/report; report-type=delivery-status; boundary="b"', '', '--b', 'Content-Type: text/plain', '', 'Failed.', '--b', 'Content-Type: message/delivery-status', '', 'Reporting-MTA: dns; mx.example.org', '', 'Final-Recipient: rfc822; '.$recipient, 'Action: failed', 'Status: 5.1.1', 'Diagnostic-Code: smtp; 550 5.1.1 No such user', '', '--b--', '']);
    }

    private function forge(string $payload): string
    {
        $encode = static fn (string $bytes): string => sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return $encode($payload).'.'.$encode(substr(hash_hmac('sha256', $payload, Newsletter::KEY, true), 0, 16));
    }

    private function bounces(BounceParser $parser): BounceHandler
    {
        return new BounceHandler($parser, $this->newsletter->subscribers, $this->newsletter->deliveries, $this->newsletter->suppressions, $this->newsletter->subscriptions, new ConfiguredSite(), $this->newsletter->clock, new Verp('bounces', 'example.com'), self::VERP_KEY);
    }

    private function subscribe(MailingList $list, string $email): Subscriber
    {
        $this->newsletter->subscriptions->subscribe($list, $email);
        $subscriber = $this->newsletter->subscribers->byEmail($list->site, $email);
        self::assertInstanceOf(Subscriber::class, $subscriber);

        return $subscriber;
    }

    private function subscriber(string $email): Subscriber
    {
        return $this->subscribe($this->newsletter->list('news', false), $email);
    }

    private function subscriberId(string $email): string
    {
        return $this->newsletter->subscribers->byEmail(Site::default(), $email)->id ?? '';
    }

    private function campaigns(): Campaigns
    {
        return new Campaigns($this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->bus, $this->newsletter->clock, $this->newsletter->sendRuns(), new ApprovalPolicy(required: false));
    }

    private function profiles(): SenderProfiles
    {
        return new SenderProfiles([
            new SenderProfile('default', 'news@example.com', 'Example'),
            new SenderProfile('promo', 'promo@example.com'),
        ], 'default');
    }

    private function composer(): MessageComposer
    {
        return new MessageComposer(new Verp('bounces', 'example.com'), self::VERP_KEY);
    }

    private function deliveryHandler(?ScriptedThrottle $throttle = null): SendDeliveryHandler
    {
        return new SendDeliveryHandler(
            $this->newsletter->deliveries,
            $this->newsletter->subscribers,
            $this->newsletter->campaigns,
            $this->newsletter->lists,
            $this->newsletter->templates,
            $this->newsletter->suppressions,
            $this->profiles(),
            $throttle ?? new ScriptedThrottle(),
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

    private function confirmationHandler(?Environment $twig = null): SendConfirmationHandler
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
            $twig ?? $this->newsletter->twig(),
            $this->newsletter->mailer,
            $this->newsletter->clock,
        );
    }
}
