<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tests\Fixtures\SwitchableScope;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The promises of every store: exact matches, bounded and ordered reads, expiry by the store's clock, all-or-nothing
 * updates, forgetting an object, purging what expired, and partitions that never meet. A store under test reads its
 * partition from `$scope`.
 */
abstract class TupleStoreTestCase extends TestCase
{
    protected MockClock $clock;

    protected SwitchableScope $scope;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->scope = new SwitchableScope();
    }

    public function testTwoPartitionsWithTheSameIdentifiersNeverMeet(): void
    {
        $store = $this->store();
        $this->scope->partition = 'tenant-a';
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann'), $this->tuple('doc:1', 'viewer', 'group:g#member', '+60 seconds')]);
        $this->scope->partition = 'tenant-b';

        self::assertSame([], $store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('member:ann')]));
        self::assertSame([], $store->subjects(self::ref('doc:1'), 'viewer', 10));
        self::assertSame([], $store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10));

        $store->update([$this->tuple('doc:1', 'viewer', 'member:ann')], [$this->tuple('doc:1', 'viewer', 'member:bob'), $this->tuple('doc:2', 'viewer', 'member:ann', '+60 seconds')]);
        $this->clock->sleep(60);

        self::assertSame(1, $store->purgeExpired());
        self::assertSame(1, $store->forget(self::ref('doc:1')));
        self::assertSame(0, $store->forget(self::ref('member:ann')));

        $this->scope->partition = 'tenant-a';

        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('member:ann')])));
        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10)));
        self::assertSame(1, $store->purgeExpired());
        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)));

        $this->scope->partition = 'Tenant-A';

        self::assertSame([], $store->subjects(self::ref('doc:1'), 'viewer', 10));
    }

    public function testATupleOfOneTenantNeverAuthorisesTheSameNamesInAnother(): void
    {
        $store = $this->store();
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('space', ['admin' => Rewrite::direct('member')]),
            TypeDefinition::of('doc', ['space' => Rewrite::direct('space'), 'viewer' => Rewrite::union(Rewrite::direct('member'), Rewrite::fromRelated('space', 'admin'))]),
        );
        $writer = new RelationWriter($model, $store);
        $checker = new RelationChecker($model, $store);
        $ann = self::ref('member:ann');

        $this->scope->partition = 'tenant-a';
        $writer->grant(self::ref('space:main'), 'admin', Subject::of($ann));
        $writer->grant(self::ref('doc:1'), 'space', Subject::of(self::ref('space:main')));
        $writer->grant(self::ref('doc:2'), 'viewer', Subject::of($ann));

        $this->scope->partition = 'tenant-b';
        $writer->grant(self::ref('doc:1'), 'space', Subject::of(self::ref('space:main')));

        self::assertFalse($checker->check($ann, 'viewer', self::ref('doc:1')));
        self::assertFalse($checker->check($ann, 'viewer', self::ref('doc:2')));
        self::assertSame([], $checker->objects($ann, 'viewer', 'doc', 10)->objects);

        $this->scope->partition = 'tenant-a';

        self::assertTrue($checker->check($ann, 'viewer', self::ref('doc:1')));
        self::assertCount(2, $checker->objects($ann, 'viewer', 'doc', 20)->objects);
    }

    public function testTheDefaultPartitionIsAPartitionOfItsOwn(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann')]);

        $this->scope->partition = 'main';

        self::assertSame([], $store->subjects(self::ref('doc:1'), 'viewer', 10));
    }

    public function testAScopeWithoutAPartitionFailsEveryCallClosed(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann', '+60 seconds')]);

        $this->clock->sleep(60);
        $this->scope->partition = null;
        $calls = [
            static function () use ($store): void {
                $store->update([], [new RelationTuple(self::ref('doc:2'), 'viewer', Subject::fromString('member:ann'))]);
            },
            static fn (): mixed => $store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('member:ann')]),
            static fn (): mixed => $store->subjects(self::ref('doc:1'), 'viewer', 10),
            static fn (): mixed => $store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10),
            static fn (): mixed => $store->forget(self::ref('doc:1')),
            $store->purgeExpired(...),
        ];

        foreach ($calls as $i => $call) {
            try {
                $call();
                self::fail(\sprintf('Call %d should have failed.', $i));
            } catch (UnscopedTuples $unscoped) {
                self::assertSame('No tenant is active.', $unscoped->getMessage());
            }
        }

        $this->scope->partition = '';
        $this->clock->modify('-1 hour');

        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)));
        self::assertSame([], $store->subjects(self::ref('doc:2'), 'viewer', 10));
    }

    public function testAPartitionAgainstTheRuleIsRefused(): void
    {
        $store = $this->store();
        $this->scope->partition = "tenant\n";

        $this->expectException(InvalidRelation::class);

        $store->subjects(self::ref('doc:1'), 'viewer', 10);
    }

    public function testTheLongestPartitionIsKept(): void
    {
        $store = $this->store();
        $this->scope->partition = str_repeat('é', 63).'ab';
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann')]);

        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)));

        $this->scope->partition = str_repeat('é', 63).'a';

        self::assertSame([], $store->subjects(self::ref('doc:1'), 'viewer', 10));
    }

    public function testAWrittenTupleIsMatchedExactly(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann'), $this->tuple('doc:1', 'editor', 'member:bob')]);

        $found = $store->matching(self::ref('doc:1'), ['viewer', 'editor'], [Subject::fromString('member:ann'), Subject::fromString('member:bob'), Subject::fromString('member:cy')]);

        self::assertSame(['doc:1#editor@member:bob', 'doc:1#viewer@member:ann'], self::keys($found));
        self::assertSame([], $store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('member:bob')]));
        self::assertSame([], $store->matching(self::ref('doc:2'), ['viewer'], [Subject::fromString('member:ann')]));
    }

    public function testIdentifiersCompareByteByByte(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:A', 'viewer', 'member:Ann')]);

        self::assertSame([], $store->matching(self::ref('doc:A'), ['viewer'], [Subject::fromString('member:ann')]));
        self::assertSame([], $store->matching(self::ref('doc:a'), ['viewer'], [Subject::fromString('member:Ann')]));
        self::assertCount(1, $store->matching(self::ref('doc:A'), ['viewer'], [Subject::fromString('member:Ann')]));
    }

    public function testSetsAndEveryAreKeptApartFromOneObject(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'group:g#member'), $this->tuple('doc:1', 'viewer', 'member:*')]);

        self::assertSame([], $store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('group:g')]));
        self::assertSame(['doc:1#viewer@group:g#member'], self::keys($store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('group:g#member')])));
        self::assertSame(['doc:1#viewer@member:*'], self::keys($store->matching(self::ref('doc:1'), ['viewer'], [Subject::every('member')])));

        $read = $store->subjects(self::ref('doc:1'), 'viewer', 10);
        self::assertTrue($read[0]->subject->isSet());
        self::assertTrue($read[1]->subject->isEvery());
        self::assertFalse($read[1]->subject->isSet());
    }

    public function testSubjectsAreOrderedBoundedAndFiltered(): void
    {
        $store = $this->store();
        $store->update([], [
            $this->tuple('doc:1', 'viewer', 'member:cy'),
            $this->tuple('doc:1', 'viewer', 'group:b#member'),
            $this->tuple('doc:1', 'viewer', 'member:ann'),
            $this->tuple('doc:1', 'viewer', 'group:a#owner'),
            $this->tuple('doc:1', 'viewer', 'group:a#member'),
            $this->tuple('doc:1', 'editor', 'member:bob'),
            $this->tuple('doc:2', 'viewer', 'member:dee'),
        ]);

        self::assertSame(
            ['doc:1#viewer@group:a#member', 'doc:1#viewer@group:a#owner', 'doc:1#viewer@group:b#member', 'doc:1#viewer@member:ann', 'doc:1#viewer@member:cy'],
            self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)),
        );
        self::assertSame(['doc:1#viewer@group:a#member', 'doc:1#viewer@group:a#owner'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 2)));
        self::assertSame(
            ['doc:1#viewer@group:a#member', 'doc:1#viewer@group:a#owner', 'doc:1#viewer@group:b#member'],
            self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10, true)),
        );
    }

    public function testObjectsAreOrderedBoundedAndExact(): void
    {
        $store = $this->store();
        $store->update([], [
            $this->tuple('doc:3', 'viewer', 'member:ann'),
            $this->tuple('doc:1', 'viewer', 'member:ann'),
            $this->tuple('doc:2', 'viewer', 'member:ann'),
            $this->tuple('doc:4', 'editor', 'member:ann'),
            $this->tuple('page:1', 'viewer', 'member:ann'),
            $this->tuple('doc:5', 'viewer', 'group:ann#member'),
            $this->tuple('doc:6', 'viewer', 'member:bob'),
        ]);

        self::assertSame(['doc:1#viewer@member:ann', 'doc:2#viewer@member:ann', 'doc:3#viewer@member:ann'], self::keys($store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10)));
        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->objects('doc', 'viewer', Subject::fromString('member:ann'), 1)));
        self::assertSame(['doc:5#viewer@group:ann#member'], self::keys($store->objects('doc', 'viewer', Subject::fromString('group:ann#member'), 10)));
    }

    public function testATupleWrittenAgainReplacesItsExpiry(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann', '+1 hour')]);
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann', '+2 hours')]);

        $found = $store->subjects(self::ref('doc:1'), 'viewer', 10);
        self::assertCount(1, $found);
        self::assertSame($this->clock->now()->modify('+2 hours')->getTimestamp(), $found[0]->expiresAt?->getTimestamp());

        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann')]);
        self::assertNull($store->subjects(self::ref('doc:1'), 'viewer', 10)[0]->expiresAt);
    }

    public function testAnExpiredTupleIsNeverRead(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann', '+60 seconds'), $this->tuple('doc:1', 'viewer', 'group:g#member', '+60 seconds')]);

        $this->clock->sleep(59);
        self::assertCount(1, $store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('member:ann')]));
        self::assertCount(2, $store->subjects(self::ref('doc:1'), 'viewer', 10));
        self::assertCount(1, $store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10));

        $this->clock->sleep(1);
        self::assertSame([], $store->matching(self::ref('doc:1'), ['viewer'], [Subject::fromString('member:ann')]));
        self::assertSame([], $store->subjects(self::ref('doc:1'), 'viewer', 10));
        self::assertSame([], $store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10));
    }

    public function testPurgeDeletesOnlyWhatExpired(): void
    {
        $store = $this->store();
        $store->update([], [
            $this->tuple('doc:1', 'viewer', 'member:ann', '+60 seconds'),
            $this->tuple('doc:2', 'viewer', 'member:ann', '+120 seconds'),
            $this->tuple('doc:3', 'viewer', 'member:ann'),
        ]);

        self::assertSame(0, $store->purgeExpired());
        $this->clock->sleep(60);
        self::assertSame(1, $store->purgeExpired());
        self::assertSame(0, $store->purgeExpired());
        $this->clock->sleep(3600);
        self::assertSame(1, $store->purgeExpired());
        self::assertSame(['doc:3#viewer@member:ann'], self::keys($store->objects('doc', 'viewer', Subject::fromString('member:ann'), 10)));
    }

    public function testAnUpdateDeletesThenWrites(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann'), $this->tuple('doc:1', 'viewer', 'member:bob')]);
        $store->update([$this->tuple('doc:1', 'viewer', 'member:ann'), $this->tuple('doc:9', 'viewer', 'member:zed')], [$this->tuple('doc:1', 'editor', 'member:ann')]);
        $store->update([], []);

        self::assertSame(['doc:1#viewer@member:bob'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)));
        self::assertSame(['doc:1#editor@member:ann'], self::keys($store->subjects(self::ref('doc:1'), 'editor', 10)));
    }

    public function testAnUpdateMayOnlyDelete(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann'), $this->tuple('doc:1', 'viewer', 'member:bob')]);
        $store->update([$this->tuple('doc:1', 'viewer', 'member:bob')], []);

        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)));
    }

    public function testATupleDeletedAndWrittenInOneUpdateIsKept(): void
    {
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann')]);
        $store->update([$this->tuple('doc:1', 'viewer', 'member:ann')], [$this->tuple('doc:1', 'viewer', 'member:ann', '+1 hour')]);

        self::assertNotNull($store->subjects(self::ref('doc:1'), 'viewer', 10)[0]->expiresAt);
    }

    public function testForgetDeletesTheTuplesOnAnObjectAndGivenToIt(): void
    {
        $store = $this->store();
        $store->update([], [
            $this->tuple('group:g', 'member', 'member:ann'),
            $this->tuple('doc:1', 'viewer', 'group:g#member'),
            $this->tuple('doc:1', 'parent', 'group:g'),
            $this->tuple('doc:2', 'viewer', 'member:ann'),
            $this->tuple('group:h', 'member', 'member:ann'),
        ]);

        self::assertSame(3, $store->forget(self::ref('group:g')));
        self::assertSame(0, $store->forget(self::ref('group:g')));
        self::assertSame([], $store->subjects(self::ref('doc:1'), 'viewer', 10));
        self::assertSame(2, $store->forget(self::ref('member:ann')));
    }

    public function testReadsAreOrderedByteByByteOnEveryPart(): void
    {
        $store = $this->store();
        $object = self::ref('doc:1');
        $parts = [];

        foreach (['a', 'a2', 'b'] as $relation) {
            foreach (['mem', 'member', 'member2'] as $kind) {
                foreach (['a', 'a1', 'ab', 'b'] as $id) {
                    foreach (['', 'x', 'x2'] as $subjectRelation) {
                        $parts[] = [$relation, $kind, $id, $subjectRelation];
                    }
                }
            }
        }

        $subject = static fn (array $part): string => $part[1].':'.$part[2].('' === $part[3] ? '' : '#'.$part[3]);
        $store->update([], array_map(fn (array $part): RelationTuple => $this->tuple('doc:1', $part[0], $subject($part)), $parts));
        $compare = static function (array $a, array $b, int $from): int {
            for ($i = $from; $i < 4; ++$i) {
                if (0 !== $result = strcmp($a[$i], $b[$i])) {
                    return $result;
                }
            }

            return 0;
        };

        $everything = $parts;
        usort($everything, static fn (array $a, array $b): int => $compare($a, $b, 0));
        $subjects = [];

        foreach ($parts as $part) {
            $subjects[$subject($part)] = Subject::fromString($subject($part));
        }

        self::assertSame(
            array_map(static fn (array $part): string => 'doc:1#'.$part[0].'@'.$subject($part), $everything),
            self::keys($store->matching($object, ['a', 'a2', 'b'], array_values($subjects))),
        );

        $single = array_values(array_filter($parts, static fn (array $part): bool => 'a2' === $part[0]));
        usort($single, static fn (array $a, array $b): int => $compare($a, $b, 1));

        self::assertSame(
            array_map(static fn (array $part): string => 'doc:1#a2@'.$subject($part), $single),
            self::keys($store->subjects($object, 'a2', 100)),
        );
    }

    abstract protected function store(): TupleStore;

    protected static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    protected function tuple(string $object, string $relation, string $subject, ?string $expires = null): RelationTuple
    {
        return new RelationTuple(self::ref($object), $relation, Subject::fromString($subject), null === $expires ? null : $this->clock->now()->modify($expires));
    }

    /**
     * @param list<RelationTuple> $tuples
     *
     * @return list<string>
     */
    protected static function keys(array $tuples): array
    {
        return array_map(static fn (RelationTuple $tuple): string => $tuple->key(), $tuples);
    }
}
