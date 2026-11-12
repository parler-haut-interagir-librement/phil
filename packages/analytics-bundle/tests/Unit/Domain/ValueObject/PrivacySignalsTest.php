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
