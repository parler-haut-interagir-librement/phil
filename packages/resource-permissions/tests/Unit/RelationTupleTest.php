<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\RelationName;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationTuple::class)]
#[CoversClass(RelationName::class)]
final class RelationTupleTest extends TestCase
{
    public function testTheKeyLeavesTheExpiryOut(): void
    {
        $tuple = new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('group:g#member'), new DateTimeImmutable('2026-12-01'));

        self::assertSame('doc:1#viewer@group:g#member', $tuple->key());
        self::assertSame('viewer', $tuple->relation);
    }

    public function testExpiryIsToTheSecond(): void
    {
        $tuple = new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:a'), new DateTimeImmutable('2026-12-01 10:00:00.900 UTC'));

        self::assertFalse($tuple->hasExpiredAt(new DateTimeImmutable('2026-12-01 09:59:59.999 UTC')));
        self::assertTrue($tuple->hasExpiredAt(new DateTimeImmutable('2026-12-01 10:00:00.000 UTC')));
        self::assertTrue($tuple->hasExpiredAt(new DateTimeImmutable('2026-12-01 10:00:01 UTC')));
        self::assertFalse(new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:a'))->hasExpiredAt(new DateTimeImmutable('2999-01-01')));
    }

    public function testARelationFollowsTheRules(): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('"Viewer" is not');

        new RelationTuple(ResourceReference::of('doc', '1'), 'Viewer', Subject::fromString('member:a'));
    }

    public function testRelationNames(): void
    {
        self::assertSame('a', RelationName::assert('a'));
        self::assertSame('allow.view_all-2', RelationName::assert('allow.view_all-2'));
        self::assertSame('a'.str_repeat('b', 63), RelationName::assert('a'.str_repeat('b', 63)));

        foreach (['', '1a', '.a', 'a'.str_repeat('b', 64), "a\n", 'a b', 'é'] as $refused) {
            try {
                RelationName::assert($refused);
                self::fail(\sprintf('"%s" should be refused.', $refused));
            } catch (InvalidRelation) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testATupleIsAboutOneObject(): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('never "*"');

        new RelationTuple(ResourceReference::of('doc', '*'), 'viewer', Subject::fromString('member:a'));
    }

    public function testTheObjectIdentifierIsUtf8(): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('UTF-8');

        new RelationTuple(ResourceReference::of('doc', "\xff"), 'viewer', Subject::fromString('member:a'));
    }

    public function testTheSubjectIdentifierIsUtf8(): void
    {
        $this->expectException(InvalidRelation::class);

        new RelationTuple(ResourceReference::of('doc', 'é'), 'viewer', Subject::of(ResourceReference::of('member', "\xc3")));
    }
}
