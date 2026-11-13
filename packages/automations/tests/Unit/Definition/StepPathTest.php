<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Definition\BranchPath;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Exception\InvalidDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StepPath::class)]
final class StepPathTest extends TestCase
{
    public function testItReadsAndWritesNestedPaths(): void
    {
        $path = StepPath::fromString('2/then/0/else/13');

        self::assertSame(2, $path->index);
        self::assertSame([[BranchPath::Then, 0], [BranchPath::Else, 13]], $path->nested);
        self::assertSame('2/then/0/else/13', (string) $path);
        self::assertSame('2/then/0/else/14', $path->sibling()->toString());
        self::assertSame('2/then/0', $path->parent()?->toString());
        self::assertSame('2/then/0/else/13/then/0', $path->into(BranchPath::Then)->toString());
        self::assertSame('0', StepPath::fromString('0')->toString());
        self::assertSame('1', StepPath::top(0)->sibling()->toString());
        self::assertNull(StepPath::top(3)->parent());
        self::assertSame('0', StepPath::top(-4)->toString());
        self::assertSame('9999', StepPath::fromString('9999')->toString());
    }

    #[DataProvider('refused')]
    public function testItRefusesWhatIsNotAPath(string $path): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a step path.', $path));

        StepPath::fromString($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'a leading zero' => ['01'];
        yield 'too large' => ['10000'];
        yield 'a missing index' => ['1/then'];
        yield 'an unknown path' => ['1/maybe/0'];
        yield 'a trailing new line' => ["1\n"];
        yield 'negative' => ['-1'];
        yield 'too deep' => ['0'.str_repeat('/then/0', 17)];
    }

    public function testItAcceptsSixteenLevels(): void
    {
        self::assertCount(16, StepPath::fromString('0'.str_repeat('/then/0', 16))->nested);
    }
}
