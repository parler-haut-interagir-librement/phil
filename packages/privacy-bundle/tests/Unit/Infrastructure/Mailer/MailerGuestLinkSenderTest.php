<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Mailer;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Infrastructure\Mailer\MailerGuestLinkSender;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(MailerGuestLinkSender::class)]
final class MailerGuestLinkSenderTest extends TestCase
{
    public function testTheLinkIsSentAtOnceToTheAddressOfTheRequest(): void
    {
        $transport = new class implements TransportInterface {
            /**
             * @var list<RawMessage>
             */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->sent[] = $message;

                return null;
            }

            public function __toString(): string
            {
                return 'memory://';
            }
        };
        $urls = self::createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')
            ->with(PrivacyRoutes::GUEST_CONFIRM, self::callback(static fn (array $parameters): bool => str_repeat('a', 32) === $parameters['id'] && 'the-token' === $parameters['token']), UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.org/privacy-choices/request/aaa?token=the-token');
        $twig = new Environment(new ArrayLoader([MailerGuestLinkSender::TEMPLATE => "{% block subject %} Subject {{ kind }} {% endblock %}\nBody {{ link }} until {{ expires_at|date('Y-m-d') }}"]));

        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        new MailerGuestLinkSender($transport, $twig, $urls, 'Privacy <privacy@example.org>')
            ->send(new GuestRequest(str_repeat('a', 32), GuestRequestKind::Erasure, 'eloise@example.org', hash('sha256', 'x'), $now, $now->modify('+1 hour')), 'the-token');

        self::assertCount(1, $transport->sent);
        $email = $transport->sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Subject erasure', $email->getSubject());
        self::assertSame('privacy@example.org', $email->getFrom()[0]->getAddress());
        self::assertSame('Privacy', $email->getFrom()[0]->getName());
        self::assertSame('eloise@example.org', $email->getTo()[0]->getAddress());
        self::assertSame(' Subject erasure Body https://example.org/privacy-choices/request/aaa?token=the-token until 2026-11-10', $email->getTextBody());
    }
}
