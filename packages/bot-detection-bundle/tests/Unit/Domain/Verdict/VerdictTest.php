<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Unit\Domain\Verdict;

use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Verdict::class)]
final class VerdictTest extends TestCase
{
    public function testTheTwoWireValuesAreRecognized(): void
    {
        self::assertSame(Verdict::Human, Verdict::fromSubmitted('human'));
        self::assertSame(Verdict::Robot, Verdict::fromSubmitted('robot'));
    }

    /**
     * Anything but the exact wire value is unknown — including a payload that smuggles more than a verdict
     * behind one, which is the shape an identifier would take if a client tried to send one.
     *
     * @return iterable<string, array{?string}>
     */
    public static function unrecognizedValues(): iterable
    {
        yield 'absent' => [null];
        yield 'empty' => [''];
        yield 'another case' => ['Robot'];
        yield 'surrounding whitespace' => [' human '];
        yield 'a longer payload' => ['human:3f2a9c'];
        yield 'a boolean as text' => ['true'];
        yield 'the unknown case itself' => ['unknown'];
    }

    #[DataProvider('unrecognizedValues')]
    public function testAnythingElseIsUnknownAndNeverThrows(?string $value): void
    {
        self::assertSame(Verdict::Unknown, Verdict::fromSubmitted($value));
    }

    public function testOnlyARobotVerdictIsARobot(): void
    {
        self::assertTrue(Verdict::Robot->isRobot());
        self::assertFalse(Verdict::Human->isRobot());
        self::assertFalse(Verdict::Unknown->isRobot());
    }
}
