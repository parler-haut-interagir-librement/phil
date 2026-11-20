<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Unit::class)]
#[CoversClass(UnitPath::class)]
#[CoversClass(UnitId::class)]
#[CoversClass(UnitMembership::class)]
final class UnitTest extends TestCase
{
    public function testAnIdentifier(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', UnitId::generate()->value);
        self::assertNotSame(UnitId::generate()->value, UnitId::generate()->value);
        self::assertTrue(UnitId::fromString('sales')->equals(UnitId::fromString('sales')));
        self::assertFalse(UnitId::fromString('sales')->equals(UnitId::fromString('Sales')));

        $this->expectExceptionMessageIsOrContains('A unit identifier is 1 to 128');

        UnitId::fromString('a/b');
    }

    public function testAPath(): void
    {
        $root = UnitPath::root($this->id('a'));
        $path = $root->child($this->id('b'))->child($this->id('c'));

        self::assertSame('/a/', $root->toString());
        self::assertSame('/a/b/c/', $path->toString());
        self::assertSame('/a/b/c0', $path->upperBound());
        self::assertSame(['a', 'b', 'c'], $path->ids);
        self::assertSame('c', $path->unitId()->value);
        self::assertSame('b', $path->parentId()?->value);
        self::assertNull($root->parentId());
        self::assertSame(['a', 'b'], array_map(static fn (UnitId $id): string => $id->value, $path->ancestorIds()));
        self::assertSame([], $root->ancestorIds());
        self::assertSame(['a', 'b', 'c'], array_map(static fn (UnitId $id): string => $id->value, $path->unitIds()));
        self::assertSame(1, $root->depth());
        self::assertSame(3, $path->depth());
        self::assertTrue($path->contains($this->id('b')));
        self::assertFalse($path->contains($this->id('d')));
        self::assertTrue($path->isWithin($root));
        self::assertTrue($path->isWithin($path));
        self::assertFalse($root->isWithin($path));
        self::assertFalse(UnitPath::fromString('/a-b/')->isWithin($root));
        self::assertTrue($path->equals(UnitPath::fromString('/a/b/c/')));
        self::assertFalse($path->equals($root));
        self::assertEquals($path, UnitPath::fromString($path->toString()));
    }

    public function testTheBoundSortsAfterEveryDescendantAndBeforeEveryOtherPath(): void
    {
        $path = UnitPath::fromString('/a/b/');
        $inside = ['/a/b/', '/a/b/c/', '/a/b/-/', '/a/b/zz/y/', '/a/b/Z.9_/'];
        $outside = ['/a/', '/a/b-c/', '/a/b./', '/a/b0/', '/a/ba/', '/a/c/', '/b/'];

        foreach ($inside as $other) {
            self::assertTrue(strcmp($other, $path->toString()) >= 0 && strcmp($other, $path->upperBound()) < 0, $other);
        }

        foreach ($outside as $other) {
            self::assertFalse(strcmp($other, $path->toString()) >= 0 && strcmp($other, $path->upperBound()) < 0, $other);
        }
    }

    public function testAPathIsSixteenLevelsDeepAtMost(): void
    {
        $path = UnitPath::root($this->id('u1'));

        for ($level = 2; $level <= UnitPath::MAX_DEPTH; ++$level) {
            $path = $path->child($this->id('u'.$level));
        }

        self::assertSame(16, $path->depth());
        self::assertEquals($path, UnitPath::fromString($path->toString()));

        try {
            $path->child($this->id('u17'));
            self::fail('A path went seventeen levels deep.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertSame('A unit is at most 16 levels deep.', $invalidOrganization->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A unit is at most 16 levels deep.');

        UnitPath::fromString($path->toString().'u17/');
    }

    public function testTheLongestPathFitsItsColumn(): void
    {
        $path = UnitPath::root($this->id(str_repeat('a', 128)));

        for ($level = 2; $level <= UnitPath::MAX_DEPTH; ++$level) {
            $path = $path->child($this->id(str_repeat(\chr(96 + $level), 128)));
        }

        self::assertSame(UnitPath::MAX_LENGTH, \strlen($path->toString()));
        self::assertSame(2065, UnitPath::MAX_LENGTH);
    }

    public function testAPathHoldsEachUnitOnce(): void
    {
        try {
            UnitPath::fromString('/a/b/')->child($this->id('a'));
            self::fail('A unit came twice on a path.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertSame('The unit "a" is already on this path.', $invalidOrganization->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A unit path holds each unit once.');

        UnitPath::fromString('/a/b/a/');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'a slash' => ['/'];
        yield 'two slashes' => ['//'];
        yield 'no leading slash' => ['a/'];
        yield 'no trailing slash' => ['/a'];
        yield 'no leading slash, long enough' => ['ab/'];
        yield 'no trailing slash, long enough' => ['/ab'];
        yield 'no slash at all, long enough' => ['abc'];
        yield 'an empty level' => ['/a//b/'];
        yield 'an e-mail address' => ['/a@b/'];
        yield 'a space' => ['/a b/'];
    }

    #[DataProvider('notPaths')]
    public function testWhatIsNotAPath(string $path): void
    {
        $this->expectException(InvalidOrganization::class);

        UnitPath::fromString($path);
    }

    public function testAShortPathIsAPath(): void
    {
        self::assertSame(['a'], UnitPath::fromString('/a/')->ids);
        self::assertSame('A unit path is written "/root/unit/"; "x" is not.', $this->message(static fn (): UnitPath => UnitPath::fromString('x')));
    }

    public function testRebase(): void
    {
        $from = UnitPath::fromString('/a/b/');
        $to = UnitPath::fromString('/x/b/');

        self::assertSame('/x/b/c/d/', UnitPath::fromString('/a/b/c/d/')->rebase($from, $to)->toString());
        self::assertSame('/x/b/', $from->rebase($from, $to)->toString());
        self::assertSame('The path "/a/c/" is not within "/a/b/".', $this->message(static fn (): UnitPath => UnitPath::fromString('/a/c/')->rebase($from, $to)));
    }

    public function testAUnitIsCreatedUnderItsParent(): void
    {
        $now = new DateTimeImmutable('2026-11-20 10:00:00.75 UTC');
        $sales = Unit::create($this->id('sales'), $this->org('o1'), OrganizationName::fromString('Sales'), null, $now);
        $east = Unit::create($this->id('east'), $this->org('o1'), OrganizationName::fromString(' East '), $sales, $now);

        self::assertSame('/sales/', $sales->path->toString());
        self::assertNull($sales->parentId());
        self::assertSame('/sales/east/', $east->path->toString());
        self::assertSame('sales', $east->parentId()?->value);
        self::assertSame('East', $east->name->value);
        self::assertTrue($east->isActive());
        self::assertSame(OrganizationStatus::Active, $east->status);
        self::assertNull($east->archivedAt);
        self::assertSame('2026-11-20 10:00:00.000000', $east->createdAt->format('Y-m-d H:i:s.u'));

        $this->expectExceptionMessageIsOrContains('The unit "sales" is of another organization.');

        Unit::create($this->id('west'), $this->org('o2'), OrganizationName::fromString('West'), $sales, $now);
    }

    public function testAUnitRenamedArchivedAndRestored(): void
    {
        $unit = Unit::create($this->id('sales'), $this->org('o1'), OrganizationName::fromString('Sales'), null, new DateTimeImmutable('@1000'));
        $unit->rename(OrganizationName::fromString('Revenue'));
        $unit->archive(new DateTimeImmutable('2026-11-20 10:00:00.5 UTC'));

        self::assertSame('Revenue', $unit->name->value);
        self::assertFalse($unit->isActive());
        self::assertSame(OrganizationStatus::Archived, $unit->status);
        self::assertSame('2026-11-20 10:00:00.000000', $unit->archivedAt?->format('Y-m-d H:i:s.u'));

        $unit->restore();

        self::assertTrue($unit->isActive());
        self::assertNull($unit->archivedAt);
    }

    public function testAUnitMoves(): void
    {
        $now = new DateTimeImmutable('@1000');
        $sales = Unit::create($this->id('sales'), $this->org('o1'), OrganizationName::fromString('Sales'), null, $now);
        $east = Unit::create($this->id('east'), $this->org('o1'), OrganizationName::fromString('East'), $sales, $now);
        $north = Unit::create($this->id('north'), $this->org('o1'), OrganizationName::fromString('North'), $east, $now);
        $support = Unit::create($this->id('support'), $this->org('o1'), OrganizationName::fromString('Support'), null, $now);

        self::assertSame('/sales/east/', $east->moveUnder($support)->toString());
        self::assertSame('/support/east/', $east->path->toString());
        self::assertSame('/support/east/', $east->moveUnder(null)->toString());
        self::assertSame('/east/', $east->path->toString());

        foreach ([$sales, $north] as $parent) {
            self::assertSame('The unit "sales" cannot move under itself or one of its descendants.', $this->message(static fn (): UnitPath => $sales->moveUnder($parent)));
        }

        $own = Unit::create($this->id('own'), $this->org('o1'), OrganizationName::fromString('Own'), $sales, $now);
        self::assertSame('The unit "sales" cannot move under itself or one of its descendants.', $this->message(static fn (): UnitPath => $sales->moveUnder($own)));
        self::assertSame('/sales/', $sales->path->toString());
    }

    public function testAUnitRestoredFromAStore(): void
    {
        $unit = Unit::restored($this->id('east'), $this->org('o1'), OrganizationName::fromString('East'), UnitPath::fromString('/sales/east/'), new DateTimeImmutable('@1000'), new DateTimeImmutable('@2000'));

        self::assertSame('sales', $unit->parentId()?->value);
        self::assertFalse($unit->isActive());
        self::assertSame(2000, $unit->archivedAt?->getTimestamp());
        self::assertTrue(Unit::restored($this->id('east'), $this->org('o1'), OrganizationName::fromString('East'), UnitPath::fromString('/east/'), new DateTimeImmutable('@1000'), null)->isActive());

        $this->expectExceptionMessageIsOrContains('The path of the unit "east" ends with another unit.');

        Unit::restored($this->id('east'), $this->org('o1'), OrganizationName::fromString('East'), UnitPath::fromString('/east/west/'), new DateTimeImmutable('@1000'), null);
    }

    public function testAUnitMembership(): void
    {
        $membership = UnitMembership::join($this->id('sales'), $this->org('o1'), MemberId::fromString('ann'), Roles::of(['manager']), new DateTimeImmutable('2026-11-20 10:00:00.5 UTC'));
        $membership->changeRoles(Roles::of(['member', 'instructor']));

        self::assertSame(['instructor', 'member'], $membership->roles->names);
        self::assertSame('2026-11-20 10:00:00.000000', $membership->joinedAt->format('Y-m-d H:i:s.u'));
        self::assertEquals($membership, UnitMembership::restored($this->id('sales'), $this->org('o1'), MemberId::fromString('ann'), Roles::of(['instructor', 'member']), $membership->joinedAt));
    }

    private function id(string $id): UnitId
    {
        return UnitId::fromString($id);
    }

    private function org(string $id): OrganizationId
    {
        return OrganizationId::fromString($id);
    }

    /**
     * @param callable(): mixed $call
     */
    private function message(callable $call): string
    {
        try {
            $call();
        } catch (InvalidOrganization $invalidOrganization) {
            return $invalidOrganization->getMessage();
        }

        self::fail('Nothing was refused.');
    }
}
