<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The values a terminal, a payment presented on it and a sale preset are made of, checked when they are made.
 */
#[CoversClass(Terminal::class)]
#[CoversClass(TerminalPayment::class)]
#[CoversClass(SalePreset::class)]
final class TerminalTest extends TestCase
{
    public function testATerminalKeepsWhatItIsMadeOf(): void
    {
        $terminal = Terminal::of('front-1', $this->register(), 'acme_pay', 'SN:0042', 'Front counter', true);

        self::assertSame(['front-1', 'front', 'acme_pay', 'SN:0042', 'Front counter', true, true], [$terminal->code, $terminal->register, $terminal->adapter, $terminal->device, $terminal->label(), $terminal->pinEntry, $terminal->isActive()]);

        $terminal->retire();
        self::assertFalse($terminal->isActive());
        $terminal->reinstate();
        self::assertTrue($terminal->isActive());
        $terminal->relabel(str_repeat('é', 64));
        self::assertSame(str_repeat('é', 64), $terminal->label());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCodes(): iterable
    {
        yield 'uppercase' => ['T1'];
        yield 'a dash first' => ['-t1'];
        yield 'a space first' => [' t1'];
        yield 'a character after' => ['t1!'];
        yield 'too long' => [str_repeat('t', 33)];
        yield 'a trailing newline' => ["t1\n"];
    }

    #[DataProvider('badCodes')]
    public function testATerminalCodeIsShortAndLowercase(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a terminal code: up to 32 lowercase letters, digits, "_" or "-".', $code));

        Terminal::of($code, $this->register(), 'acme_pay', 'SN:0042', 'Front counter', false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badAdapters(): iterable
    {
        yield 'uppercase' => ['Fake'];
        yield 'a digit first' => ['1fake'];
        yield 'a space first' => [' fake'];
        yield 'a dash' => ['fake-pay'];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'a trailing newline' => ["fake\n"];
    }

    #[DataProvider('badAdapters')]
    public function testAnAdapterIsNamedInLowercase(string $adapter): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not an adapter name: up to 32 lowercase letters, digits or "_".', $adapter));

        Terminal::of('t1', $this->register(), $adapter, 'SN:0042', 'Front counter', false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badReferences(): iterable
    {
        yield 'empty' => [''];
        yield 'a space first' => [' SN'];
        yield 'a space last' => ['SN '];
        yield 'a space inside' => ['S N'];
        yield 'too long' => [str_repeat('d', 256)];
        yield 'a trailing newline' => ["SN\n"];
    }

    #[DataProvider('badReferences')]
    public function testADeviceIsVisibleAscii(string $device): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs("A terminal's device is named in 1 to 255 visible ASCII characters.");

        Terminal::of('t1', $this->register(), 'acme_pay', $device, 'Front counter', false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLabels(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('é', 65)];
    }

    #[DataProvider('badLabels')]
    public function testATerminalIsLabelled(string $label): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('A terminal is labelled in 1 to 64 characters.');

        Terminal::of('t1', $this->register(), 'acme_pay', 'SN:0042', $label, false);
    }

    #[DataProvider('badLabels')]
    public function testATerminalIsRelabelledWithALabel(string $label): void
    {
        $terminal = Terminal::of('t1', $this->register(), 'acme_pay', 'SN:0042', 'Front counter', false);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('A terminal is labelled in 1 to 64 characters.');

        $terminal->relabel($label);
    }

    public function testAPaymentPresentedKeepsItsProcessorsReference(): void
    {
        [$terminal, $session, $at] = $this->presentable();

        $presented = TerminalPayment::of('p-1', $terminal, $session, 'ch_3Ab!~', $at);

        self::assertSame(['p-1', 't1', $session->id, 'ch_3Ab!~', $at], [$presented->payment, $presented->terminal, $presented->session, $presented->processorReference, $presented->presentedAt]);
        self::assertFalse($presented->isCounted());
        self::assertTrue($presented->count());
        self::assertFalse($presented->count());
        self::assertTrue($presented->isCounted());
    }

    #[DataProvider('badReferences')]
    public function testAProcessorsReferenceIsVisibleAscii(string $reference): void
    {
        [$terminal, $session, $at] = $this->presentable();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs("A processor's reference is 1 to 255 visible ASCII characters.");

        TerminalPayment::of('p-1', $terminal, $session, $reference, $at);
    }

    public function testAPresetSellsToNobodyInParticularByDefault(): void
    {
        $preset = SalePreset::of('walk-in', 'Walk-in', null);

        self::assertSame(['walk-in', 'Walk-in', null, null, false, true], [$preset->code, $preset->label(), $preset->channel(), $preset->customerGroup(), $preset->identifiesCustomer(), $preset->isActive()]);
    }

    #[DataProvider('badCodes')]
    public function testAPresetCodeIsShortAndLowercase(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a preset code: up to 32 lowercase letters, digits, "_" or "-".', $code));

        SalePreset::of($code, 'Walk-in', null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badChannels(): iterable
    {
        yield 'empty' => [''];
        yield 'a space first' => [' web'];
        yield 'a space last' => ['web '];
        yield 'too long' => [str_repeat('c', 65)];
        yield 'a trailing newline' => ["web\n"];
    }

    #[DataProvider('badChannels')]
    public function testAPresetsChannelAndGroupAreReferences(string $reference): void
    {
        $message = \sprintf('"%s" is not a channel or a customer group: 1 to 64 visible ASCII characters.', $reference);

        foreach ([static fn (): SalePreset => SalePreset::of('walk-in', 'Walk-in', $reference), static fn (): SalePreset => SalePreset::of('walk-in', 'Walk-in', null, $reference)] as $make) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    private function register(): Register
    {
        return Register::of('front', 'Front', Currency::of('CAD'));
    }

    /**
     * @return array{Terminal, CashSession, DateTimeImmutable}
     */
    private function presentable(): array
    {
        $at = new DateTimeImmutable('2026-11-22 10:00:00');
        $register = $this->register();
        [$session] = CashSession::open($register, 'ada', Money::of(0, Currency::of('CAD')), $at);

        return [Terminal::of('t1', $register, 'acme_pay', 'SN:0042', 'Front counter', false), $session, $at];
    }
}
