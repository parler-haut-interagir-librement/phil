<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Cmi5\MoveOn;
use Phil\ElearningPackages\Exception\ElearningPackagesException;
use Phil\ElearningPackages\Exception\InvalidLimits;
use Phil\ElearningPackages\Exception\PackageRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoveOn::class)]
#[CoversClass(InvalidLimits::class)]
#[CoversClass(PackageRefused::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{MoveOn, list<bool>}>
     */
    public static function rules(): iterable
    {
        // Completed and passed, completed and failed, completed alone, passed alone, failed alone, nothing.
        yield 'Completed' => [MoveOn::Completed, [true, true, true, false, false, false]];
        yield 'Passed' => [MoveOn::Passed, [true, false, false, true, false, false]];
        yield 'CompletedAndPassed' => [MoveOn::CompletedAndPassed, [true, false, false, false, false, false]];
        yield 'CompletedOrPassed' => [MoveOn::CompletedOrPassed, [true, true, true, true, false, false]];
        yield 'NotApplicable' => [MoveOn::NotApplicable, [true, true, true, true, true, true]];
    }

    /**
     * @param list<bool> $expected
     */
    #[DataProvider('rules')]
    public function testAMoveOnRuleIsMetAsCmi5Says(MoveOn $moveOn, array $expected): void
    {
        self::assertSame($expected, [
            $moveOn->isMetBy(true, true),
            $moveOn->isMetBy(true, false),
            $moveOn->isMetBy(true, null),
            $moveOn->isMetBy(false, true),
            $moveOn->isMetBy(false, false),
            $moveOn->isMetBy(false, null),
        ]);
    }

    public function testTheExceptionsShareOneInterface(): void
    {
        self::assertInstanceOf(ElearningPackagesException::class, PackageRefused::because('A'));
        self::assertSame('A', PackageRefused::because('A')->getMessage());
        self::assertInstanceOf(ElearningPackagesException::class, InvalidLimits::because('B'));
        self::assertSame('B', InvalidLimits::because('B')->getMessage());
    }
}
