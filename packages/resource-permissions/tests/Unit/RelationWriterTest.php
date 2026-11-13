<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnknownRelation;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tests\Unit\Model\ModelTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(RelationWriter::class)]
final class RelationWriterTest extends TestCase
{
    public function testGrantAndRevoke(): void
    {
        $store = new InMemoryTupleStore(new MockClock('2026-11-13 10:00:00 UTC'));
        $writer = new RelationWriter(ModelTest::model(), $store);
        $expires = new DateTimeImmutable('2026-12-01 00:00:00 UTC');

        $tuple = $writer->grant(ResourceReference::of('doc', '1'), 'viewer', ResourceReference::of('member', 'ann'), $expires);
        $writer->grant(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('group:g#member'));

        self::assertSame('doc:1#viewer@member:ann', $tuple->key());
        self::assertSame($expires, $tuple->expiresAt);
        self::assertCount(2, $store->subjects(ResourceReference::of('doc', '1'), 'viewer', 10));

        $writer->revoke(ResourceReference::of('doc', '1'), 'viewer', ResourceReference::of('member', 'ann'));
        $writer->revoke(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('group:g#member'));
        self::assertSame([], $store->subjects(ResourceReference::of('doc', '1'), 'viewer', 10));
    }

    public function testATupleTheModelDoesNotAcceptIsRefused(): void
    {
        $store = new InMemoryTupleStore(new MockClock());
        $writer = new RelationWriter(ModelTest::model(), $store);

        try {
            $writer->update([], [
                new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:ann')),
                new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('group:g')),
            ]);
            self::fail('The update should be refused.');
        } catch (InvalidRelation $invalidRelation) {
            self::assertSame('The model does not accept group:g on doc#viewer.', $invalidRelation->getMessage());
        }

        self::assertSame([], $store->subjects(ResourceReference::of('doc', '1'), 'viewer', 10));
    }

    public function testARevokeOfAnUnknownRelationIsRefused(): void
    {
        $store = new InMemoryTupleStore(new MockClock());
        $store->update([], [new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:ann'))]);

        try {
            new RelationWriter(ModelTest::model(), $store)->update([
                new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:ann')),
                new RelationTuple(ResourceReference::of('doc', '1'), 'owner', Subject::fromString('member:ann')),
            ], []);
            self::fail('The update should be refused.');
        } catch (UnknownRelation) {
            self::assertCount(1, $store->subjects(ResourceReference::of('doc', '1'), 'viewer', 10));
        }
    }

    public function testAnUpdateIsBounded(): void
    {
        $writer = new RelationWriter(ModelTest::model(), new InMemoryTupleStore(new MockClock()));
        $tuple = new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:ann'));
        $writer->update(array_fill(0, 500, $tuple), array_fill(0, 500, $tuple));

        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('An update writes or deletes at most 1000 tuples.');

        $writer->update(array_fill(0, 500, $tuple), array_fill(0, 501, $tuple));
    }

    public function testForget(): void
    {
        $store = new InMemoryTupleStore(new MockClock());
        $writer = new RelationWriter(ModelTest::model(), $store);
        $writer->grant(ResourceReference::of('doc', '1'), 'viewer', ResourceReference::of('member', 'ann'));
        $writer->grant(ResourceReference::of('group', 'g'), 'member', ResourceReference::of('member', 'ann'));

        self::assertSame(2, $writer->forget(ResourceReference::of('member', 'ann')));
    }
}
