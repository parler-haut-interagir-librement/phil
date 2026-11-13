<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Domain;

use DateInterval;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberId::class)]
#[CoversClass(SharingLevels::class)]
#[CoversClass(Visibility::class)]
#[CoversClass(AccessGroup::class)]
#[CoversClass(InvalidAccess::class)]
final class DomainTest extends TestCase
{
    public function testAMember(): void
    {
        $member = MemberId::of('01JABC-x_y.z');

        self::assertSame('member:01JABC-x_y.z', $member->reference()->toString());
        self::assertTrue($member->equals(MemberId::of('01JABC-x_y.z')));
        self::assertFalse($member->equals(MemberId::of('01JABD')));
        self::assertSame('a'.str_repeat('b', 127), MemberId::of('a'.str_repeat('b', 127))->value);
        self::assertSame('7', MemberId::of('7')->value);
    }

    public function testAMemberIsNeverAnEmailAddress(): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIsOrContains('never an email address');

        MemberId::of('ann@example.org');
    }

    #[DataProvider('invalidMembers')]
    public function testAMemberIdentifierFollowsTheRules(string $member): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIsOrContains('A member identifier is 1 to 128');

        MemberId::of($member);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidMembers(): iterable
    {
        yield [''];
        yield ['-a'];
        yield ['a b'];
        yield ['a#b'];
        yield ['a:b'];
        yield ['a'.str_repeat('b', 128)];
        yield ["a\n"];
    }

    public function testSharingLevels(): void
    {
        $levels = SharingLevels::of(SharingLevels::DEFAULT);

        self::assertSame(['manage', 'edit', 'comment', 'view'], $levels->names);
        self::assertSame('view', $levels->narrowest());
        self::assertSame('comment', $levels->wider('view'));
        self::assertSame('manage', $levels->wider('edit'));
        self::assertNull($levels->wider('manage'));
        self::assertTrue($levels->has('edit'));
        self::assertFalse($levels->has('owner'));
        self::assertSame('edit', $levels->assert('edit'));
        self::assertSame('read', SharingLevels::of(['read'])->narrowest());
        self::assertCount(16, SharingLevels::of(array_map(static fn (int $i): string => 'l'.$i, range(1, 16)))->names);
        self::assertSame(['a'.str_repeat('b', 31)], SharingLevels::of(['a'.str_repeat('b', 31)])->names);
    }

    public function testAnUnknownLevelIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs('"publish" is not a sharing level of the site; they are manage, edit, comment, view.');

        SharingLevels::of(SharingLevels::DEFAULT)->assert('publish');
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('invalidLevels')]
    public function testInvalidLevelsAreRefused(array $names, string $message): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIsOrContains($message);

        SharingLevels::of($names);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidLevels(): iterable
    {
        yield 'none' => [[], '1 to 16 sharing levels'];
        yield 'too many' => [array_map(static fn (int $i): string => 'l'.$i, range(1, 17)), '1 to 16 sharing levels'];
        yield 'reserved' => [['owner'], 'none of owner, space, visibility; "owner" is not.'];
        yield 'space' => [['space'], '"space" is not'];
        yield 'visibility' => [['visibility'], '"visibility" is not'];
        yield 'upper case' => [['View'], '"View" is not'];
        yield 'dot' => [['a.b'], '"a.b" is not'];
        yield 'too long' => [['a'.str_repeat('b', 32)], 'is not'];
        yield 'twice' => [['view', 'view'], 'A sharing level is named once.'];
    }

    public function testVisibilitiesOfALink(): void
    {
        self::assertSame(Visibility::Members, Visibility::Everyone->linking(Visibility::Members));
        self::assertSame(Visibility::Members, Visibility::Members->linking(Visibility::Everyone));
        self::assertSame(Visibility::Everyone, Visibility::Everyone->linking(Visibility::Everyone));
        self::assertSame(Visibility::Admins, Visibility::Members->linking(Visibility::Admins));
        self::assertSame(Visibility::Owner, Visibility::Admins->linking(Visibility::Owner));
        self::assertSame(Visibility::Owner, Visibility::Everyone->linking(Visibility::Owner));
    }

    public function testAnAccessGroup(): void
    {
        $group = AccessGroup::of('supporters', [EntryPoint::Purchase, EntryPoint::Invitation, EntryPoint::Purchase], new DateInterval('PT1S'));

        self::assertSame('supporters', $group->name);
        self::assertSame([EntryPoint::Purchase, EntryPoint::Invitation], $group->entryPoints);
        self::assertTrue($group->accepts(EntryPoint::Invitation));
        self::assertFalse($group->accepts(EntryPoint::Default));
        self::assertNull(AccessGroup::of('a', [EntryPoint::Bulk])->duration);
    }

    public function testAnAccessGroupWithoutEntryPointIsRefused(): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIs('The access group "g" declares at least one entry point.');

        AccessGroup::of('g', []);
    }

    public function testAnAccessGroupNameFollowsTheRules(): void
    {
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIsOrContains('"G" is not');

        AccessGroup::of('G', [EntryPoint::Bulk]);
    }

    public function testAMembershipLastsAtLeastASecond(): void
    {
        $negative = new DateInterval('P1D');
        $negative->invert = 1;

        foreach ([new DateInterval('PT0S'), $negative] as $duration) {
            try {
                AccessGroup::of('g', [EntryPoint::Bulk], $duration);
                self::fail('The duration should be refused.');
            } catch (InvalidAccess $invalid) {
                self::assertSame('The membership of "g" lasts at least a second.', $invalid->getMessage());
            }
        }
    }
}
