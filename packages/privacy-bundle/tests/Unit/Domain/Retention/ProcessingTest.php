<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Retention;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\Processing;
use Phil\PrivacyBundle\Domain\Retention\Processor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Processing::class)]
#[CoversClass(Processor::class)]
final class ProcessingTest extends TestCase
{
    public function testAProcessingSaysWhyWhoAndWhere(): void
    {
        $processing = new Processing('Running the membership', ['Membership team', 'Accountant'], [new Processor('Host', 'Canada')], 'Canada');

        self::assertSame('Running the membership', $processing->purpose);
        self::assertSame(['Membership team', 'Accountant'], $processing->recipients);
        self::assertSame('Host (Canada)', $processing->processors[0]->toString());
        self::assertTrue($processing->isComplete());
        self::assertFalse(new Processing('Why')->isComplete());
        self::assertFalse(new Processing(location: 'Canada')->isComplete());
        self::assertFalse(new Processing()->isComplete());
    }

    public function testTheLimitsAreInclusive(): void
    {
        self::assertSame(1000, mb_strlen((string) new Processing(str_repeat('é', 1000))->purpose));
        self::assertSame(200, mb_strlen((string) new Processing(location: str_repeat('é', 200))->location));
        self::assertSame(200, mb_strlen(new Processor(str_repeat('é', 200), 'x')->name));
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty purpose' => [static fn (): Processing => new Processing(' '), 'A purpose is a text of 1 to 1000 characters.'];
        yield 'long purpose' => [static fn (): Processing => new Processing(str_repeat('a', 1001)), 'A purpose is a text of 1 to 1000 characters.'];
        yield 'empty location' => [static fn (): Processing => new Processing(location: ''), 'A location is one line of at most 200 characters.'];
        yield 'long location' => [static fn (): Processing => new Processing(location: str_repeat('a', 201)), 'A location is one line of at most 200 characters.'];
        yield 'two lines' => [static fn (): Processing => new Processing(location: "a\nb"), 'A location is one line of at most 200 characters.'];
        yield 'empty recipient' => [static fn (): Processing => new Processing(recipients: ['']), 'A recipient is one line of at most 200 characters.'];
        yield 'blank recipient' => [static fn (): Processing => new Processing(recipients: ['  ']), 'A recipient is one line of at most 200 characters.'];
        yield 'twice a recipient' => [static fn (): Processing => new Processing(recipients: ['A', 'A']), 'The recipient "A" is listed twice.'];
        yield 'twice a processor' => [static fn (): Processing => new Processing(processors: [new Processor('A', 'x'), new Processor('A', 'y')]), 'The processor "A" is listed twice.'];
        yield 'empty processor name' => [static fn (): Processor => new Processor(' ', 'x'), "A processor's name is one line of at most 200 characters."];
        yield 'long processor location' => [static fn (): Processor => new Processor('A', str_repeat('a', 201)), "A processor's location is one line of at most 200 characters."];
        yield 'control in a processor' => [static fn (): Processor => new Processor("A\x7F", 'x'), "A processor's name is one line of at most 200 characters."];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refused')]
    public function testWhatIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
