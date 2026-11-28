<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Exception;

use Phil\Rules\Exception\InvalidRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidRule::class)]
final class InvalidRuleTest extends TestCase
{
    public function testShowsANameShortAndWithoutControlCharacters(): void
    {
        self::assertSame('a?b', InvalidRule::shown("a\x7Fb"));
        self::assertSame('a?b', InvalidRule::shown("a\x00b"));
        self::assertSame(str_repeat('é', 64), InvalidRule::shown(str_repeat('é', 70)));
    }

    public function testNamesTheVersionRead(): void
    {
        self::assertSame('The rule is of version 2; this library reads version 1.', InvalidRule::unsupportedVersion(2)->getMessage());
        self::assertSame('The rule is of version string; this library reads version 1.', InvalidRule::unsupportedVersion('1')->getMessage());
        self::assertSame('The rule is of version null; this library reads version 1.', InvalidRule::unsupportedVersion(null)->getMessage());
    }
}
