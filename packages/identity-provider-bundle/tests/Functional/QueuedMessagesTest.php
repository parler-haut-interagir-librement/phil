<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Override;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Infrastructure\Mailer\MailerAccountMailer;
use Phil\IdentityProviderBundle\Infrastructure\Notifier\NotifierSmsSender;
use Phil\IdentityProviderBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * An application whose Messenger routes the emails and the text messages to an asynchronous transport, as the recipe
 * of Messenger does: the links of verification, of reset and of sign-in, and the codes sent by text message, leave in
 * the request, and nothing of them waits in the queue.
 */
#[CoversClass(MailerAccountMailer::class)]
#[CoversClass(NotifierSmsSender::class)]
final class QueuedMessagesTest extends FunctionalTestCase
{
    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(
            identity: ['sms' => ['sender' => null]],
            framework: [
                'messenger' => [
                    'transports' => ['async' => 'in-memory://'],
                    'routing' => [SendEmailMessage::class => 'async', SmsMessage::class => 'async'],
                ],
                'notifier' => ['texter_transports' => ['null' => 'null://null']],
            ],
        );
    }

    public function testTheRoutingQueuesTheEmailsAndTextMessagesPutOnTheBus(): void
    {
        $bus = $this->service(MessageBusInterface::class, 'messenger.default_bus');
        $bus->dispatch(new SendEmailMessage(new Email()->from('a@example.com')->to('b@example.com')->subject('Hello')->text('Hello')));
        $bus->dispatch(new SmsMessage('+15145550123', 'Hello'));

        self::assertSame([SendEmailMessage::class, SmsMessage::class], array_map(static fn (Envelope $envelope): string => $envelope->getMessage()::class, $this->queue()->getSent()), 'The routing of the test application queues the emails and text messages put on the bus, as `MailerInterface` and the texter `texter` do.');
    }

    public function testNoLinkNorCodeWaitsInTheQueue(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create the account')->form(['email' => 'ada@example.com', 'password' => self::PASSWORD, 'name' => 'Ada']));
        $verification = $this->linkOfTheLastEmail();
        self::assertStringStartsWith('http://localhost/verify-email?account=', $verification);

        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Send the link')->form(['email' => 'ada@example.com']));
        $reset = $this->linkOfTheLastEmail();
        self::assertStringStartsWith('http://localhost/reset-password?account=', $reset);

        $crawler = $this->client->request('GET', '/login/link');
        $this->client->submit($crawler->selectButton('Send me a link')->form(['email' => 'ada@example.com']));
        $signIn = $this->linkOfTheLastEmail();
        self::assertStringStartsWith('http://localhost/login/link/check?user=', $signIn);

        $this->accountVerified();
        $this->signIn();
        $crawler = $this->client->request('GET', '/account/phone');
        $this->client->submit($crawler->selectButton('Send a code')->form(['phone' => '+1 514 555 0123']));
        self::assertResponseRedirects('/account/phone', 303);
        $sms = self::getNotifierMessage();
        self::assertNotNull($sms, 'The code left through the texter transport.');
        self::assertSame('+15145550123', $sms->getRecipientId());
        self::assertMatchesRegularExpression('/\b\d{6}\b/', $sms->getSubject());

        self::assertSame([], array_map(static fn (Envelope $envelope): string => $envelope->getMessage()::class, $this->queue()->getSent()), 'Nothing waits in the queue: no link, no code, no address.');
        self::assertSame([], $this->queue()->getRejected());
    }

    private function queue(): InMemoryTransport
    {
        return $this->service(InMemoryTransport::class, 'messenger.transport.async');
    }

    /**
     * The link of the last email, read from its text part, which writes it in full; the email carries no tracking.
     */
    private function linkOfTheLastEmail(): string
    {
        $emails = self::getMailerMessages();
        $email = end($emails);
        self::assertInstanceOf(Email::class, $email);
        $tracking = TrackingHeader::fromHeaders($email->getHeaders());
        self::assertInstanceOf(TrackingHeader::class, $tracking);
        self::assertSame([false, false], [$tracking->getOpens(), $tracking->getClicks()]);
        self::assertSame(1, preg_match('#^http://localhost/\S+$#m', (string) $email->getTextBody(), $link), 'The text part writes the link in full, on its own line.');
        preg_match('#href="([^"]+)"#', (string) $email->getHtmlBody(), $href);
        self::assertSame(html_entity_decode($href[1] ?? ''), $link[0], 'The text part and the HTML part carry the same link.');

        return $link[0];
    }

    private function accountVerified(): void
    {
        $account = $this->accounts()->findByEmail('ada@example.com');
        self::assertNotNull($account);
        $this->service(Accounts::class)->verifyEmail($account);
    }
}
