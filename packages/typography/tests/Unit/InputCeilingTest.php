<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Phil\Typography\Exception\InputTooLarge;
use Phil\Typography\Exception\TypographyException;
use Phil\Typography\Fixer\Ellipsis;
use Phil\Typography\Html\Blocks;
use Phil\Typography\Html\Collector;
use Phil\Typography\Html\Serializer;
use Phil\Typography\Typographer;
use Phil\Typography\Typographers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The ceiling of every call that takes a text: a text of the ceiling's length is fixed, one byte more is refused
 * whole, before it is parsed, by an `InputTooLarge` that says both lengths.
 */
#[CoversClass(InputTooLarge::class)]
#[CoversClass(Typographer::class)]
#[CoversClass(Typographers::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Serializer::class)]
#[CoversClass(Collector::class)]
final class InputCeilingTest extends TestCase
{
    public function testEachCallFixesUpToTheCeilingAndRefusesMore(): void
    {
        $typographer = new Typographer([new Ellipsis()], maxBytes: 9);

        self::assertSame('<p>ab…</p>', $typographer->fix('<p>ab...'));
        self::assertSame('ab…cd', $typographer->fixText('ab...cd'));
        self::assertStringContainsString('<p>ab…</p>', $typographer->fixDocument('<p>ab...'));
        $this->assertRefused(10, 9, static fn (): mixed => $typographer->fix('<p>ab...xy'));
        $this->assertRefused(10, 9, static fn (): mixed => $typographer->fixDocument('<p>ab...xy'));
        $this->assertRefused(10, 9, static fn (): mixed => $typographer->fixText('abcdefghi.'));
    }

    public function testThePresetsAndTheTypographersOfEachLocaleHaveTheCeiling(): void
    {
        $this->assertRefused(10, 9, static fn (): mixed => Typographer::for('en', maxBytes: 9)->fixText('0123456789'));
        self::assertSame('012345678', Typographer::for('en', maxBytes: 9)->fixText('012345678'));

        $typographers = new Typographers('en', maxBytes: 9);
        self::assertSame('<p>ab…</p>', $typographers->fixHtml('<p>ab...'));
        $this->assertRefused(10, 9, static fn (): mixed => $typographers->fixHtml('<p>ab...xy'));
        $this->assertRefused(10, 9, static fn (): mixed => $typographers->for('fr')->fixText('0123456789'));
    }

    /**
     * By default, a text of 1 MiB is fixed, and one byte more refused, before anything else is done with it.
     */
    public function testTheDefaultCeilingIsOneMebibyte(): void
    {
        self::assertSame(1_048_576, Typographer::DEFAULT_MAX_BYTES);

        $typographer = new Typographer([]);
        self::assertSame(1_048_576, \strlen($typographer->fixText(str_repeat('a', 1_048_576))));
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => $typographer->fixText(str_repeat('a', 1_048_577)));
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => $typographer->fix(str_repeat('<', 1_048_577)));
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => Typographer::for('en')->fixDocument(str_repeat('<', 1_048_577)));
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => new Typographers()->fixHtml(str_repeat('a', 1_048_577)));
        $this->assertRefused(1_048_577, 1_048_576, static fn (): mixed => new Typographers()->for()->fixText(str_repeat('a', 1_048_577)));
    }

    /**
     * The ceiling counts bytes: a character of two bytes counts twice.
     */
    public function testTheCeilingCountsBytes(): void
    {
        self::assertSame('éé', new Typographer([], maxBytes: 4)->fixText('éé'));
        $this->assertRefused(6, 4, static fn (): mixed => new Typographer([], maxBytes: 4)->fixText('ééé'));
    }

    public function testTheExceptionSaysBothLengths(): void
    {
        $exception = InputTooLarge::of(12, 10);

        self::assertSame('The input is 12 bytes long, above the limit of 10 bytes.', $exception->getMessage());
        self::assertSame([12, 10], [$exception->bytes, $exception->limit]);
        self::assertInstanceOf(TypographyException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        InputTooLarge::check('1234567890', 10);
        $this->assertRefused(11, 10, static function (): null {
            InputTooLarge::check('12345678901', 10);

            return null;
        });
    }

    /**
     * @param Closure(): mixed $call
     */
    private function assertRefused(int $bytes, int $limit, Closure $call): void
    {
        try {
            $call();
            self::fail(\sprintf('A text of %d bytes should be refused above %d.', $bytes, $limit));
        } catch (InputTooLarge $inputTooLarge) {
            self::assertSame([$bytes, $limit], [$inputTooLarge->bytes, $inputTooLarge->limit]);
        }
    }
}
