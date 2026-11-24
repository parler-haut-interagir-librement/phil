<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\PrivacySignals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrivacySignals::class)]
final class PrivacySignalsTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, string|null, bool}>
     */
    public static function headers(): iterable
    {
        yield 'none' => [null, null, false];
        yield 'do not track' => ['1', null, true];
        yield 'global privacy control' => [null, '1', true];
        yield 'both' => ['1', '1', true];
        yield 'do not track off' => ['0', null, false];
        yield 'padded value' => [' 1 ', null, true];
        yield 'padded global privacy control' => [null, ' 1 ', true];
        yield 'unrecognized value' => ['yes', 'true', false];
        yield 'doubled global privacy control' => [null, '0,1', true];
        yield 'global privacy control list' => [null, '0, 1', true];
        yield 'global privacy control first of a list' => [null, '1,0', true];
        yield 'global privacy control spaces' => [null, "\t1 , 0", true];
        yield 'empty global privacy control' => [null, '', false];
        yield 'empty values of global privacy control' => [null, ', ,', false];
        yield 'global privacy control zeros' => [null, '0, 0', false];
        yield 'global privacy control one inside a value' => [null, '10, 01', false];
        yield 'do not track is not a list' => ['0, 1', null, false];
    }

    #[DataProvider('headers')]
    public function testOnlyTheValueOneIsAnOptOut(?string $dnt, ?string $gpc, bool $optOut): void
    {
        self::assertSame($optOut, PrivacySignals::fromHeaders($dnt, $gpc)->optOut());
    }

    public function testNoSignalIsNoOptOut(): void
    {
        self::assertFalse(PrivacySignals::none()->optOut());
    }
}
