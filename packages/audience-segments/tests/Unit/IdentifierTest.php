<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit;

use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\AudienceSegments\Identifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(InvalidSegment::class)]
final class IdentifierTest extends TestCase
{
    public function testTellsAnIdentifierFromAnythingElse(): void
    {
        self::assertTrue(Identifier::valid('m01'));
        self::assertTrue(Identifier::valid('spring-fair:2026/05_02.a'));
        self::assertFalse(Identifier::valid(''));
        self::assertFalse(Identifier::valid('-m01'));
        self::assertFalse(Identifier::valid('ana@example.com'));
        self::assertFalse(Identifier::valid("m01\n"));
    }

    public function testChecksAnIdentifier(): void
    {
        self::assertSame('m01', Identifier::check('member', 'm01'));

        $this->expectException(InvalidSegment::class);

        Identifier::check('member', 'ana@example.com');
    }
}
