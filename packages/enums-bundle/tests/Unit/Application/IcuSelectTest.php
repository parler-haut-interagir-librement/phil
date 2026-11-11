<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Application;

use LogicException;
use MessageFormatter;
use Phil\EnumsBundle\Application\Icu\IcuSelect;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use Phil\EnumsBundle\Tests\Unit\Icu\Dotted;
use Phil\EnumsBundle\Tests\Unit\Icu\Hyphenated;
use Phil\EnumsBundle\Tests\Unit\Icu\Otherwise;
use Phil\EnumsBundle\Tests\Unit\Icu\Spaced;
use Phil\EnumsBundle\Tests\Unit\Icu\Twins;
use Phil\EnumsBundle\Tests\Unit\Icu\Underscored;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use UnitEnum;

#[CoversClass(IcuSelect::class)]
final class IcuSelectTest extends TestCase
{
    public function testEachCaseIsABranchKeyedByItsValue(): void
    {
        self::assertSame(
            '{status, select, pending {Pending} in_transit {In transit} cancelled {Cancelled} refunded {REFUNDED} other {}}',
            IcuSelect::of(OrderStatus::class, 'status'),
        );
        self::assertSame('{access, select, 1 {Read} 2 {Write} 4 {Admin} other {none}}', IcuSelect::of(Access::class, 'access', other: 'none'));
    }

    public function testAPureEnumIsKeyedByItsNameInSnakeCase(): void
    {
        self::assertSame('{shape, select, circle {Round} big_square {BigSquare} blank {Blank} éclair {Éclair} other {}}', IcuSelect::of(Shape::class, 'shape'));
    }

    public function testTheTextComesFromTheCallbackAndIsQuoted(): void
    {
        self::assertSame(
            "{a, select, 1 {It''s '{'Read'}'} 2 {It''s '{'Write'}'} 4 {It''s '{'Admin'}'} other {'{'?'}'}}",
            IcuSelect::of(Access::class, 'a', static fn (UnitEnum $case): string => "It's {".$case->name.'}', '{?}'),
        );
    }

    /**
     * What the exporter writes, `MessageFormatter` reads.
     */
    #[RequiresPhpExtension('intl')]
    public function testTheMessageIsOneIcuAccepts(): void
    {
        $message = IcuSelect::of(Underscored::class, 'status');

        self::assertSame('{status, select, in_progress {InProgress} done {Done} other {}}', $message);
        self::assertSame('InProgress', MessageFormatter::formatMessage('en', $message, ['status' => 'in_progress']));
        self::assertSame('éclair', mb_strtolower((string) MessageFormatter::formatMessage('fr', IcuSelect::of(Shape::class, 'shape'), ['shape' => 'éclair'])));
    }

    /**
     * @return iterable<string, array{class-string<UnitEnum>, string}>
     */
    public static function refusedEnums(): iterable
    {
        yield 'a hyphen' => [Hyphenated::class, 'The key "in-progress" of '.Hyphenated::class.'::InProgress is not an ICU keyword'];
        yield 'a dot' => [Dotted::class, 'The key "v1.2" of '.Dotted::class.'::First is not an ICU keyword'];
        yield 'a space' => [Spaced::class, 'The key "a b" of '.Spaced::class.'::Open is not an ICU keyword'];
        yield 'other' => [Otherwise::class, 'The key of '.Otherwise::class.'::Other is "other"'];
        yield 'twice' => [Twins::class, Twins::class.' has two cases keyed "foo_bar"'];
    }

    /**
     * @param class-string<UnitEnum> $enum
     */
    #[DataProvider('refusedEnums')]
    public function testAKeyIcuWouldRefuseIsRefusedByName(string $enum, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($message);

        IcuSelect::of($enum, 'value');
    }

    public function testAnArgumentIcuWouldRefuseIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"order-status" cannot name an ICU argument');

        IcuSelect::of(Underscored::class, 'order-status');
    }

    public function testAnArgumentEndingInANewlineIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot name an ICU argument');

        IcuSelect::of(Underscored::class, "status\n");
    }
}
