<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit;

use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;
use Phil\Poll\CountingMethods;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\Exception\PollException;
use Phil\Poll\Exception\UnknownCountingMethod;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CountingMethods::class)]
#[CoversClass(UnknownCountingMethod::class)]
final class CountingMethodsTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $methods = CountingMethods::defaults();

        self::assertInstanceOf(ChoiceCount::class, $methods->get('choices'));
        self::assertInstanceOf(InstantRunoff::class, $methods->get('instant_runoff'));
        self::assertInstanceOf(Borda::class, $methods->get('borda'));
        self::assertInstanceOf(Schulze::class, $methods->get('schulze'));
        self::assertSame(['choices'], $methods->namesFor(BallotKind::Single));
        self::assertSame(['choices'], $methods->namesFor(BallotKind::Multiple));
        self::assertSame(['instant_runoff', 'borda', 'schulze'], $methods->namesFor(BallotKind::Ranked));
        self::assertTrue($methods->has('borda'));
        self::assertFalse($methods->has('approval'));
        self::assertSame('choices', CountingMethods::defaultFor(BallotKind::Single));
        self::assertSame('choices', CountingMethods::defaultFor(BallotKind::Multiple));
        self::assertSame('instant_runoff', CountingMethods::defaultFor(BallotKind::Ranked));
    }

    public function testACountByName(): void
    {
        $result = CountingMethods::defaults()->count('borda', PollRules::ranked(['a', 'b']), Ballots::of([[1, ['b', 'a']]]));

        self::assertSame(['borda', ['b']], [$result->method, $result->winners()]);
    }

    public function testAnUnknownName(): void
    {
        $this->expectExceptionObject(UnknownCountingMethod::named('approval'));

        try {
            new CountingMethods([])->get('approval');
        } catch (UnknownCountingMethod $unknownCountingMethod) {
            self::assertInstanceOf(PollException::class, $unknownCountingMethod);
            self::assertSame('No counting method is named "approval".', $unknownCountingMethod->getMessage());

            throw $unknownCountingMethod;
        }
    }

    public function testAMethodOfTheApplication(): void
    {
        $methods = new CountingMethods([new ChoiceCount(), $this->method('123'), $this->method(str_repeat('m', 64))]);

        self::assertSame(['choices', '123', str_repeat('m', 64)], $methods->namesFor(BallotKind::Single));
        self::assertSame(['123', str_repeat('m', 64)], $methods->namesFor(BallotKind::Ranked));
    }

    #[TestWith(['Borda'])]
    #[TestWith([''])]
    #[TestWith(["borda\n"])]
    #[TestWith(['mmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmm'])]
    public function testAMethodIsNamed(string $name): void
    {
        $this->expectExceptionObject(InvalidPoll::because(\sprintf('A counting method is named by 1 to 64 lowercase ASCII letters, digits or "_", not "%s".', $name)));

        new CountingMethods([$this->method($name)]);
    }

    public function testANameIsDeclaredOnce(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The counting method "choices" is declared twice.'));

        new CountingMethods([new ChoiceCount(), new Borda(), $this->method('choices')]);
    }

    private function method(string $name): CountingMethod
    {
        return new readonly class($name) implements CountingMethod {
            public function __construct(
                private string $name,
            ) {
            }

            #[Override]
            public function name(): string
            {
                return $this->name;
            }

            #[Override]
            public function supports(BallotKind $kind): bool
            {
                return true;
            }

            #[Override]
            public function count(PollRules $rules, iterable $ballots): Result
            {
                return new Result($this->name, 0, 0, []);
            }
        };
    }
}
