<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Mailer;

use Generator;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Mailer\ConfiguredTenantMailSettings;
use Phil\TenancyBundle\Infrastructure\Mailer\TenantTransports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(TenantTransports::class)]
final class TenantTransportsTest extends TestCase
{
    public function testAMessageSentInATenantLeavesTheTenantsWay(): void
    {
        $inner = $this->recording();
        $email = new Email()->to('ana@mail.example')->text('Hello');

        self::assertNull($this->transports($inner, 'fair')->send($email));

        self::assertSame($email, $inner->sent[0]);
        self::assertSame('fair_smtp', $email->getHeaders()->get('X-Transport')?->getBodyAsString());
        self::assertSame('"Summer Fair" <mail@platform.example>', $email->getFrom()[0]->toString());
        self::assertSame('fair@mail.example', $email->getReplyTo()[0]->toString());
    }

    public function testWhatTheMessageSaysWins(): void
    {
        $inner = $this->recording();
        $email = new Email()->from('team@fair.example')->replyTo('desk@fair.example')->to('ana@mail.example')->text('Hello');
        $email->getHeaders()->addTextHeader('X-Transport', 'urgent');

        $this->transports($inner, 'fair')->send($email);

        self::assertSame('urgent', $email->getHeaders()->get('X-Transport')?->getBodyAsString());
        self::assertSame(['team@fair.example'], array_map(static fn (Address $address): string => $address->toString(), $email->getFrom()));
        self::assertSame(['desk@fair.example'], array_map(static fn (Address $address): string => $address->toString(), $email->getReplyTo()));
    }

    public function testATenantWithoutATransportOrAReplyAddressAddsNone(): void
    {
        $email = new Email()->to('ana@mail.example')->text('Hello');

        $this->transports($this->recording(), 'rose')->send($email);

        self::assertFalse($email->getHeaders()->has('X-Transport'));
        self::assertFalse($email->getHeaders()->has('Reply-To'));
        self::assertSame('shop@rose.example', $email->getFrom()[0]->toString());
    }

    public function testAMessageInNoTenantOrInATenantWithoutSettingsLeavesAsTheApplications(): void
    {
        foreach ([null, 'elsewhere'] as $tenant) {
            $email = new Email()->to('ana@mail.example')->text('Hello');

            $this->transports($this->recording(), $tenant)->send($email);

            self::assertSame(['To'], iterator_to_array($this->names($email), false));
        }
    }

    public function testARawMessageIsSentAsItIs(): void
    {
        $inner = $this->recording();
        $message = new RawMessage('raw');

        $this->transports($inner, 'fair')->send($message);

        self::assertSame([$message], $inner->sent);
    }

    public function testTheTransportsReadAsTheInnerOnes(): void
    {
        self::assertSame('[main,fair_smtp]', (string) $this->transports($this->recording(), null));
    }

    /**
     * @return Generator<string>
     */
    private function names(Email $email): Generator
    {
        foreach ($email->getHeaders()->all() as $header) {
            self::assertInstanceOf(HeaderInterface::class, $header);

            yield $header->getName();
        }
    }

    private function transports(TransportInterface $inner, ?string $tenant): TenantTransports
    {
        $context = new TenantContext();

        if (null !== $tenant) {
            $context->enter(TenantId::fromString($tenant));
        }

        return new TenantTransports($inner, $context, new ConfiguredTenantMailSettings('"Platform" <mail@platform.example>', [
            'fair' => ['transport' => 'fair_smtp', 'name' => 'Summer Fair', 'reply_to' => 'fair@mail.example'],
            'rose' => ['sender' => 'shop@rose.example'],
        ]));
    }

    /**
     * @return TransportInterface&object{sent: list<RawMessage>}
     */
    private function recording(): TransportInterface
    {
        return new class implements TransportInterface {
            /** @var list<RawMessage> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->sent[] = $message;

                return null;
            }

            public function __toString(): string
            {
                return '[main,fair_smtp]';
            }
        };
    }
}
