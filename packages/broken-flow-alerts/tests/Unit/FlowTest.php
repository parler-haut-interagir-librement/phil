<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit;

use Phil\BrokenFlowAlerts\Exception\InvalidFlowValue;
use Phil\BrokenFlowAlerts\Flow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flow::class)]
#[CoversClass(InvalidFlowValue::class)]
final class FlowTest extends TestCase
{
    public function testTheLabelIsTheIdentifierUnlessGiven(): void
    {
        self::assertSame('checkout', Flow::of('checkout')->label);
        self::assertSame('Checkout', Flow::of('checkout', 'Checkout')->label);
        self::assertSame('checkout', Flow::of('checkout', 'Checkout')->id);
    }

    public function testAnIdentifierOfTheMaximumLengthIsAccepted(): void
    {
        self::assertSame(128, \strlen(Flow::of(str_repeat('a', 128))->id));
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty identifier' => ['', null, 'A flow identifier is 1 to 128 bytes; this one is 0.'];
        yield 'long identifier' => [str_repeat('a', 129), null, 'A flow identifier is 1 to 128 bytes; this one is 129.'];
        yield 'empty label' => ['checkout', '', 'A flow label is 1 to 128 bytes; this one is 0.'];
        yield 'long label' => ['checkout', str_repeat('a', 129), 'A flow label is 1 to 128 bytes; this one is 129.'];
        yield 'leading space' => [' checkout', null, 'A flow identifier has no surrounding whitespace and no control character.'];
        yield 'trailing space' => ['checkout ', null, 'A flow identifier has no surrounding whitespace and no control character.'];
        yield 'control character' => ["check\nout", null, 'A flow identifier has no surrounding whitespace and no control character.'];
        yield 'delete character' => ["check\x7Fout", null, 'A flow identifier has no surrounding whitespace and no control character.'];
        yield 'label with control character' => ['checkout', "Check\tout", 'A flow label has no surrounding whitespace and no control character.'];
    }

    #[DataProvider('refused')]
    public function testARefusedValueSaysWhy(string $id, ?string $label, string $message): void
    {
        $this->expectException(InvalidFlowValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Flow::of($id, $label);
    }
}
