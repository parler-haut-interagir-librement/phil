<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommercePosBundle\Application\TerminalCharge;
use Phil\CommercePosBundle\Infrastructure\Terminal\FakePaymentTerminal;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FakePaymentTerminal::class)]
final class FakePaymentTerminalTest extends TestCase
{
    private const string SECRET = 'sixteen bytes ok';

    public function testItRemembersWhatItWasAskedUntilReset(): void
    {
        $terminal = new FakePaymentTerminal(self::SECRET);
        $charge = new TerminalCharge('p-1', 'A-1', 'tmr_1', $this->cad(100));

        self::assertSame('fake', $terminal->name());
        self::assertSame('fake_1_p-1', $terminal->present($charge));
        self::assertSame('fake_2_p-2', $terminal->present(new TerminalCharge('p-2', 'A-2', 'tmr_1', $this->cad(5))));
        $terminal->cancel('tmr_1', 'fake_1_p-1');
        $terminal->refund('fake_2_p-2', $this->cad(5), 'refund-1');

        self::assertSame([$charge, 'p-2'], [$terminal->presented()[0], $terminal->presented()[1]->payment]);
        self::assertSame(['fake_1_p-1'], $terminal->cancelled());
        self::assertSame(['fake_2_p-2', 5, 'refund-1'], [$terminal->refunded()[0][0], $terminal->refunded()[0][1]->minorUnits, $terminal->refunded()[0][2]]);

        $terminal->reset();

        self::assertSame([[], [], []], [$terminal->presented(), $terminal->cancelled(), $terminal->refunded()]);
        self::assertSame('fake_3_p-1', $terminal->present($charge));
    }

    public function testItReadsTheNotificationsItSigned(): void
    {
        $terminal = new FakePaymentTerminal(self::SECRET);
        [$payload, $headers] = $terminal->notification('evt-1', 'p-1', PaymentEvent::Captured);

        self::assertSame('{"id":"evt-1","payment":"p-1","event":"captured"}', $payload);
        self::assertSame([FakePaymentTerminal::SIGNATURE_HEADER => [hash_hmac('sha256', $payload, hash_hkdf('sha256', self::SECRET, 32, FakePaymentTerminal::USAGE))]], $headers);
        self::assertSame('phil_commerce_pos.fake_terminal', FakePaymentTerminal::USAGE);
        self::assertNotSame([hash_hmac('sha256', $payload, self::SECRET)], $headers[FakePaymentTerminal::SIGNATURE_HEADER]);
        self::assertTrue($terminal->recognises($headers));
        self::assertFalse($terminal->recognises(['stripe-signature' => ['x']]));

        $notification = $terminal->read($payload, $headers);
        self::assertSame(['evt-1', 'p-1', PaymentEvent::Captured, null], [$notification->id, $notification->externalReference, $notification->event, $notification->amount]);

        [$payload, $headers] = $terminal->notification('evt-2', 'p-1', PaymentEvent::Refunded, $this->cad(250));
        $refund = $terminal->read($payload, $headers);
        self::assertSame([PaymentEvent::Refunded, 250, 'CAD'], [$refund->event, $refund->amount?->minorUnits, $refund->amount?->currency->code]);
    }

    public function testAnAmountIsReadForARefundOnly(): void
    {
        $terminal = new FakePaymentTerminal(self::SECRET);

        self::assertNull($terminal->read(...$this->signed('{"id":"e","payment":"p","event":"captured","amount":5,"currency":"CAD"}'))->amount);
    }

    public function testANotificationAsDeepAsAllowedIsRead(): void
    {
        $notification = new FakePaymentTerminal(self::SECRET)->read(...$this->signed('{"id":"e","payment":"p","event":"captured","meta":{"a":{"b":1}}}'));

        self::assertSame(['e', PaymentEvent::Captured], [$notification->id, $notification->event]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not JSON' => ['{', "The fake terminal's notification is not JSON."];
        yield 'too deep' => ['{"a":{"b":{"c":{"d":1}}}}', "The fake terminal's notification is not JSON."];
        yield 'not an object' => ['"a"', "The fake terminal's notification names an id, a payment and an event."];
        yield 'no id' => ['{"payment":"p","event":"captured"}', "The fake terminal's notification names an id, a payment and an event."];
        yield 'no payment' => ['{"id":"e","event":"captured"}', "The fake terminal's notification names an id, a payment and an event."];
        yield 'unknown event' => ['{"id":"e","payment":"p","event":"paid"}', "The fake terminal's notification names an id, a payment and an event."];
        yield 'event not a text' => ['{"id":"e","payment":"p","event":1}', "The fake terminal's notification names an id, a payment and an event."];
        yield 'refund without amount' => ['{"id":"e","payment":"p","event":"refunded","currency":"CAD"}', "The fake terminal's refund carries its amount and currency."];
        yield 'refund without currency' => ['{"id":"e","payment":"p","event":"refunded","amount":5}', "The fake terminal's refund carries its amount and currency."];
        yield 'refund in no currency' => ['{"id":"e","payment":"p","event":"refunded","amount":5,"currency":"ZZZ"}', "The fake terminal's refund carries an unknown currency."];
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableNotificationIsRefused(string $payload, string $message): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains($message);

        new FakePaymentTerminal(self::SECRET)->read(...$this->signed($payload));
    }

    public function testASignatureUnderAnotherSecretIsRefused(): void
    {
        [$payload, $headers] = new FakePaymentTerminal('another secret, sixteen')->notification('e', 'p', PaymentEvent::Captured);

        foreach ([$headers, []] as $sent) {
            try {
                new FakePaymentTerminal(self::SECRET)->read($payload, $sent);
                self::fail('A notification signed otherwise was read.');
            } catch (InvalidNotification $invalid) {
                self::assertSame("The fake terminal's signature does not verify.", $invalid->getMessage());
            }
        }
    }

    public function testItSignsWithALongEnoughSecret(): void
    {
        self::assertSame('fake', new FakePaymentTerminal(str_repeat('s', 16))->name());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('a secret of 16 bytes or more');

        new FakePaymentTerminal(str_repeat('s', 15));
    }

    /**
     * @return array{string, array<string, list<string>>}
     */
    private function signed(string $payload): array
    {
        return [$payload, [FakePaymentTerminal::SIGNATURE_HEADER => [hash_hmac('sha256', $payload, hash_hkdf('sha256', self::SECRET, 32, FakePaymentTerminal::USAGE))]]];
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
