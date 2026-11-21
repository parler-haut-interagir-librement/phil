<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Http\DigestLinks;
use Phil\NotificationsBundle\Infrastructure\Http\Routes;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerDigestSender;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Logs;
use Phil\NotificationsBundle\Tests\Unit\Fixture\MailerTransport;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(MailerDigestSender::class)]
final class MailerDigestSenderTest extends TestCase
{
    public function testTheDigestIsATemplatedEmail(): void
    {
        $mailer = $this->mailer();
        new MailerDigestSender($mailer, Translator::make(), 'notifications@example.test', 'digest.html.twig')->send(new Recipient('ada@example.test'), DigestFrequency::Daily, [$this->item('n1'), $this->item('n2')], 'fr');

        self::assertCount(1, $mailer->sent);
        $email = $mailer->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('ada@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('notifications@example.test', $email->getFrom()[0]->getAddress());
        self::assertSame('2 aujourd’hui', $email->getSubject());
        self::assertSame('digest.html.twig', $email->getHtmlTemplate());
        self::assertSame('fr', $email->getLocale());
        self::assertSame(['frequency' => 'daily', 'notifications' => [$this->item('n1')->toArray(), $this->item('n2')->toArray()], 'preferences_url' => null, 'unsubscribe_url' => null], $email->getContext());
        self::assertSame(MailerDigestSender::TEXT_TEMPLATE, $email->getTextTemplate());
        self::assertFalse($email->getHeaders()->has('List-Unsubscribe'));
        self::assertFalse($email->getHeaders()->has('List-Unsubscribe-Post'));
    }

    public function testTheMailersOwnSenderAndTheDefaultTemplate(): void
    {
        $mailer = $this->mailer();
        new MailerDigestSender($mailer, Translator::make())->send(new Recipient('ada@example.test'), DigestFrequency::Weekly, [$this->item('n1')], null);
        $email = $mailer->sent[0];

        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame([], $email->getFrom());
        self::assertSame(MailerDigestSender::TEMPLATE, $email->getHtmlTemplate());
        self::assertNull($email->getLocale());
        self::assertSame('digest.subject.weekly', $email->getSubject());
    }

    public function testTheLinksToThePreferencesAndToStopTheDigest(): void
    {
        $mailer = $this->mailer();
        $routes = new RouteCollection();
        $routes->add('app_settings', new Route('/settings/notifications'));
        $routes->add(Routes::DIGEST_UNSUBSCRIBE, new Route('/notifications/digest/unsubscribe/{recipient}'));

        $signer = new UriSigner('a secret of the tests', clock: new MockClock('2026-11-08 10:00:00 UTC'));
        $links = new DigestLinks(new UrlGenerator($routes, RequestContext::fromUri('https://example.test')), $signer, 'app_settings');

        new MailerDigestSender($mailer, Translator::make(), links: $links, textTemplate: 'digest.txt.twig')->send(new Recipient('ada@example.test'), DigestFrequency::Daily, [$this->item('n1')], null);
        $email = $mailer->sent[0];

        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('digest.txt.twig', $email->getTextTemplate());
        $context = $email->getContext();
        self::assertSame('https://example.test/settings/notifications', $context['preferences_url']);
        $unsubscribe = $context['unsubscribe_url'];
        self::assertIsString($unsubscribe);
        self::assertStringStartsWith('https://example.test/notifications/digest/unsubscribe/42?_expiration=1825668000&_hash=', $unsubscribe);
        self::assertTrue($signer->check($unsubscribe));
        self::assertSame('<'.$unsubscribe.'>', $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());
        self::assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());
    }

    public function testOpensAndClicksAreNotFollowedByDefault(): void
    {
        $mailer = $this->mailer();
        new MailerDigestSender($mailer, Translator::make())->send(new Recipient('ada@example.test'), DigestFrequency::Daily, [$this->item('n1')], null);
        $email = $mailer->sent[0];

        self::assertInstanceOf(TemplatedEmail::class, $email);
        $header = TrackingHeader::fromHeaders($email->getHeaders());
        self::assertInstanceOf(TrackingHeader::class, $header);
        self::assertFalse($header->getOpens());
        self::assertFalse($header->getClicks());
        self::assertSame('opens=false; clicks=false', $email->getHeaders()->get(TrackingHeader::NAME)?->getBodyAsString());
    }

    public function testTheConsentOfTheRecipientIsAsked(): void
    {
        $mailer = $this->mailer();
        $consent = new class implements EmailTrackingConsent {
            /** @var list<string> */
            public array $asked = [];

            #[Override]
            public function opens(string $recipient): bool
            {
                $this->asked[] = 'opens:'.$recipient;

                return '42' === $recipient;
            }

            #[Override]
            public function clicks(string $recipient): bool
            {
                $this->asked[] = 'clicks:'.$recipient;

                return false;
            }
        };
        new MailerDigestSender($mailer, Translator::make(), tracking: $consent)->send(new Recipient('ada@example.test'), DigestFrequency::Daily, [$this->item('n1'), $this->item('n2')], null);
        $email = $mailer->sent[0];

        self::assertInstanceOf(TemplatedEmail::class, $email);
        $header = TrackingHeader::fromHeaders($email->getHeaders());
        self::assertInstanceOf(TrackingHeader::class, $header);
        self::assertTrue($header->getOpens());
        self::assertFalse($header->getClicks());
        self::assertSame(['opens:42', 'clicks:42'], $consent->asked);
    }

    public function testARecipientWithoutAddressIsSkipped(): void
    {
        $mailer = $this->mailer();
        $logs = new Logs();
        $sender = new MailerDigestSender($mailer, Translator::make(), logger: $logs);
        $sender->send(new NoRecipient(), DigestFrequency::Daily, [$this->item('n1')], null);
        $sender->send(new Recipient('', '+15550000000'), DigestFrequency::Daily, [$this->item('n1')], null);
        new MailerDigestSender($mailer, Translator::make())->send(new NoRecipient(), DigestFrequency::Daily, [$this->item('n1')], null);

        self::assertSame([], $mailer->sent);
        self::assertSame(['notice: A daily digest was not sent: the recipient has no email address.', 'notice: A daily digest was not sent: the recipient has no email address.'], $logs->records);
    }

    public function testTheDigestIsRenderedBeforeItLeavesAtOnce(): void
    {
        $transport = new MailerTransport();
        $renderer = new class implements BodyRendererInterface {
            /** @var list<Message> */
            public array $rendered = [];

            #[Override]
            public function render(Message $message): void
            {
                $this->rendered[] = $message;
            }
        };
        new MailerDigestSender($transport, Translator::make(), renderer: $renderer)->send(new Recipient('ada@example.test'), DigestFrequency::Daily, [$this->item('n1')], null);

        self::assertCount(1, $renderer->rendered);
        self::assertSame($transport->sent[0], $renderer->rendered[0]);
    }

    public function testARefusalOfTheTransportGoesUpForTheRetry(): void
    {
        $this->expectException(TransportException::class);

        new MailerDigestSender(MailerTransport::refusing(), Translator::make())->send(new Recipient('ada@example.test'), DigestFrequency::Daily, [$this->item('n1')], null);
    }

    private function item(string $id): RenderedNotification
    {
        return new RenderedNotification(new Notification($id, 'welcome', '42', null, new DateTimeImmutable('2026-11-08 10:00 UTC')), 'T '.$id, 'B');
    }

    private function mailer(): MailerTransport
    {
        return new MailerTransport();
    }
}
