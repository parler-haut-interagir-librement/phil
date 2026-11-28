<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Infrastructure;

use Phil\ReceiptEmail\Application\ReceiptEmails;
use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Application\TransactionalCheck;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts;
use Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer;
use Phil\ReceiptEmail\Infrastructure\Messenger\SendDonationReceipt;
use Phil\ReceiptEmail\Infrastructure\Messenger\SendDonationReceiptHandler;
use Phil\ReceiptEmail\Infrastructure\Messenger\SendPaymentReceipt;
use Phil\ReceiptEmail\Infrastructure\Messenger\SendPaymentReceiptHandler;
use Phil\ReceiptEmail\Tests\Support\Commerce;
use Phil\ReceiptEmail\Tests\Support\Donations;
use Phil\ReceiptEmail\Tests\Support\FixedRecipients;
use Phil\ReceiptEmail\Tests\Support\InMemoryDonationReceipts;
use Phil\ReceiptEmail\Tests\Support\InMemoryReceipts;
use Phil\ReceiptEmail\Tests\Support\RecordingTransport;
use Phil\ReceiptEmail\Tests\Support\Templates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Mime\Address;

/**
 * The messages are routed to an asynchronous transport, serialized as a real transport would, and handled by a worker
 * that reads the receipt again and sends its email through a Mailer transport.
 */
#[CoversClass(SendPaymentReceipt::class)]
#[CoversClass(SendDonationReceipt::class)]
#[CoversClass(SendPaymentReceiptHandler::class)]
#[CoversClass(SendDonationReceiptHandler::class)]
final class MessengerTest extends TestCase
{
    public function testTheMessagesCarryIdentifiersOnlyAndTheWorkerSendsTheEmails(): void
    {
        $queue = new InMemoryTransport(new PhpSerializer());
        $mailer = new RecordingTransport();
        $emails = new ReceiptEmails(
            new CommercePaymentReceipts(new InMemoryReceipts(Commerce::receipt())),
            new InMemoryDonationReceipts(Donations::receipt()),
            new FixedRecipients(new Recipient(new Address('ada.lovelace@example.com', 'Ada Lovelace'), 'en')),
            new EmailTemplatesRenderer(Templates::renderer()),
            new Address('shop@example.com'),
            new TransactionalCheck(),
        );
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator(
                [SendPaymentReceipt::class => ['async'], SendDonationReceipt::class => ['async']],
                new ServiceLocator(['async' => static fn (): InMemoryTransport => $queue]),
            )),
            new HandleMessageMiddleware(new HandlersLocator([
                SendPaymentReceipt::class => [new SendPaymentReceiptHandler($emails, $mailer)],
                SendDonationReceipt::class => [new SendDonationReceiptHandler($emails, $mailer)],
            ])),
        ]);

        $bus->dispatch(new SendPaymentReceipt('R-000042'));
        $bus->dispatch(new SendDonationReceipt('D-2026-000017'));

        $sentBefore = \count($mailer->sent());
        $queued = $queue->getSent();
        self::assertCount(2, $queued);

        foreach ($queued as $envelope) {
            $serialized = serialize(new PhpSerializer()->encode($envelope));

            foreach (['ada', 'Lovelace', '@', 'example.com', 'Tea', '56.74', '110.00', 'Reading Circle', 'Montréal'] as $personal) {
                self::assertStringNotContainsStringIgnoringCase($personal, $serialized, 'A message carries identifiers only.');
            }
        }

        while ([] !== $received = [...$queue->get()]) {
            foreach ($received as $envelope) {
                $bus->dispatch($envelope->with(new ReceivedStamp('async')));
                $queue->ack($envelope);
            }
        }

        $sent = $mailer->sent();
        self::assertSame(0, $sentBefore, 'Nothing is sent before the worker runs.');
        self::assertCount(2, $sent);
        self::assertSame('Receipt R-000042 from The Shop', $sent[0]->getSubject());
        self::assertSame('ada.lovelace@example.com', $sent[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('Total: 56.74 CAD', (string) $sent[0]->getTextBody());
        self::assertSame('Official donation receipt D-2026-000017 from The Reading Circle', $sent[1]->getSubject());
        self::assertStringContainsString('Eligible amount of the gift: 110.00 CAD', (string) $sent[1]->getTextBody());
    }

    public function testAMessageKeepsWhetherToAttachThePdf(): void
    {
        self::assertFalse(new SendPaymentReceipt('R-1')->attachPdf);
        self::assertTrue(new SendPaymentReceipt('R-1', true)->attachPdf);
        self::assertSame('R-1', new SendPaymentReceipt('R-1')->receiptNumber);
        self::assertFalse(new SendDonationReceipt('D-1')->attachPdf);
        self::assertTrue(new SendDonationReceipt('D-1', true)->attachPdf);
        self::assertSame('D-1', new SendDonationReceipt('D-1')->serialNumber);
        self::assertSame(str_repeat('D', 32), new SendDonationReceipt(str_repeat('D', 32))->serialNumber);
    }

    public function testAMessageRefusesAnythingButANumber(): void
    {
        try {
            new SendPaymentReceipt('ada@example.com Ada');
            self::fail('A text with a space is refused.');
        } catch (InvalidReceipt $invalidReceipt) {
            self::assertSame('The number of a receipt is 1 to 64 visible ASCII characters.', $invalidReceipt->getMessage());
        }

        $this->expectExceptionMessageIs('The serial number of a donation receipt is 1 to 32 visible ASCII characters.');

        new SendDonationReceipt(str_repeat('D', 33));
    }
}
