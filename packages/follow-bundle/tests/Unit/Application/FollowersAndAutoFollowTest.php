<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use Phil\FollowBundle\Application\AutoFollow;
use Phil\FollowBundle\Application\FollowerList;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\NoParents;
use Phil\FollowBundle\Application\OpenAccess;
use Phil\FollowBundle\Application\OpenFollowing;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Followers::class)]
#[CoversClass(FollowerList::class)]
#[CoversClass(AutoFollow::class)]
#[CoversClass(SubjectChain::class)]
#[CoversClass(NoParents::class)]
#[CoversClass(OpenAccess::class)]
#[CoversClass(OpenFollowing::class)]
final class FollowersAndAutoFollowTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testTheFollowersArePagedAndIgnoringIsNotFollowing(): void
    {
        $profile = Stage::subject('member', 'z');
        $follows = $this->stage->follows();

        foreach (['c', 'a', 'd', 'b'] as $member) {
            $follows->follow($member, $profile);
        }

        $follows->follow('e', $profile, FollowLevel::Ignoring);
        $followers = $this->stage->followers();

        self::assertEquals(new FollowerList(false, ['a', 'b', 'c', 'd'], 4), $followers->followersOf($profile, null));
        self::assertEquals(new FollowerList(false, ['a', 'b'], 4, 'b'), $followers->followersOf($profile, 'x', null, 2));
        self::assertEquals(new FollowerList(false, ['c', 'd'], 4), $followers->followersOf($profile, 'x', 'b', 2));
        self::assertEquals(new FollowerList(false, ['a'], 4, 'a'), $followers->followersOf($profile, 'x', null, 0));
        self::assertSame(4, $followers->count($profile, null));
    }

    public function testAMemberWhoHidesTheirFollowersShowsThemToThemselvesAlone(): void
    {
        $profile = Stage::subject('member', 'z');
        $this->stage->follows()->follow('a', $profile);
        $followers = $this->stage->followers();
        $followers->hideFollowers('z', true);

        self::assertTrue($followers->followersHidden('z'));
        self::assertEquals(new FollowerList(hidden: true), $followers->followersOf($profile, 'a'));
        self::assertEquals(new FollowerList(hidden: true), $followers->followersOf($profile, null));
        self::assertNull($followers->count($profile, 'a'));
        self::assertSame(['a'], $followers->followersOf($profile, 'z')->members);
        self::assertSame(1, $followers->count($profile, 'z'));

        $followers->hideFollowers('z', false);
        self::assertSame(['a'], $followers->followersOf($profile, 'a')->members);
    }

    public function testTheDefaultHidesTheFollowersOfMembersWhoNeverChose(): void
    {
        $followers = $this->stage->followers(true);
        $this->stage->follows()->follow('a', Stage::subject('member', 'z'));
        $this->stage->follows()->follow('a', Stage::subject('thread', 'z'));

        self::assertTrue($followers->isHidden(Stage::subject('member', 'z'), 'a'));
        self::assertFalse($followers->isHidden(Stage::subject('thread', 'z'), 'a'), 'Only members hide their followers.');
        $followers->hideFollowers('y', false);
        self::assertFalse($followers->followersHidden('y'));
        self::assertTrue($followers->followersHidden('x'));
    }

    public function testWhatAMemberCreatesOrRepliesToIsFollowedWhenTurnedOn(): void
    {
        $auto = $this->stage->autoFollow(level: FollowLevel::Mentions);

        self::assertTrue($auto->created('a', Stage::subject('thread', '1')));
        self::assertSame(FollowLevel::Mentions, $this->stage->follows->find('a', Stage::subject('thread', '1'))?->level());
        self::assertTrue($auto->replied('a', Stage::subject('thread', '2')));
        self::assertFalse($auto->replied('a', Stage::subject('thread', '2')), 'A member who follows already is left as they are.');
    }

    public function testAutoFollowIsOffByDefaultAndKeepsTheMembersChoices(): void
    {
        $off = $this->stage->autoFollow(false, false);
        self::assertFalse($off->created('a', Stage::subject('thread', '1')));
        self::assertFalse($off->replied('a', Stage::subject('thread', '1')));
        self::assertTrue($this->stage->autoFollow(false, true)->replied('a', Stage::subject('thread', '1')));
        self::assertTrue($this->stage->autoFollow(true, false)->created('a', Stage::subject('thread', '9')));
        self::assertNull($this->stage->follows->find('b', Stage::subject('thread', '1')));

        $auto = $this->stage->autoFollow();
        $this->stage->follows()->follow('b', Stage::subject('thread', '2'), FollowLevel::Ignoring);
        self::assertFalse($auto->replied('b', Stage::subject('thread', '2')));
        self::assertSame(FollowLevel::Ignoring, $this->stage->follows->find('b', Stage::subject('thread', '2'))?->level());

        $this->stage->ports->approvers['group:1'] = 'owner';
        self::assertFalse($auto->created('b', Stage::subject('group', '1')));
        $this->stage->ports->refused = ['c'];
        self::assertFalse($auto->created('c', Stage::subject('thread', '3')));
        self::assertFalse($auto->created('d', Stage::subject('member', 'd')));
        self::assertTrue($auto->created('d', Stage::subject('member', 'e')));
    }

    public function testTheChainStopsAtItsDepthAndAtARepeat(): void
    {
        $subject = Stage::subject('reply', '1');
        $parents = [Stage::subject('thread', '1'), Stage::subject('forum', '1'), Stage::subject('category', '1')];
        $this->stage->ports->parents['reply:1'] = $parents;
        $keys = static fn (array $chain): array => array_map(static fn (SubjectReference $s): string => $s->key(), $chain);

        self::assertSame(['reply:1', 'thread:1', 'forum:1', 'category:1'], $keys(new SubjectChain($this->stage->ports, 3)->of($subject)));
        self::assertSame(['reply:1', 'thread:1', 'forum:1'], $keys(new SubjectChain($this->stage->ports, 2)->of($subject)));
        self::assertSame(['reply:1'], $keys(new SubjectChain($this->stage->ports, 0)->of($subject)));
        self::assertSame(['reply:1'], $keys(new SubjectChain()->of($subject)));

        $this->stage->ports->parents['reply:1'] = [Stage::subject('thread', '1'), Stage::subject('reply', '1'), Stage::subject('forum', '1')];
        self::assertSame(['reply:1', 'thread:1'], $keys(new SubjectChain($this->stage->ports, 5)->of($subject)));
        $this->stage->ports->parents['reply:1'] = [Stage::subject('thread', '1'), Stage::subject('thread', '1')];
        self::assertSame(['reply:1', 'thread:1'], $keys(new SubjectChain($this->stage->ports, 5)->of($subject)));
    }

    public function testTheDepthIsBounded(): void
    {
        self::assertInstanceOf(SubjectChain::class, new SubjectChain(depth: 16));

        foreach ([-1, 17] as $depth) {
            try {
                new SubjectChain(depth: $depth);
                self::fail('Refused.');
            } catch (InvalidFollow $invalidFollow) {
                self::assertSame('The depth of the parents is 0 to 16.', $invalidFollow->getMessage());
            }
        }
    }

    public function testTheDefaultsOpenEverything(): void
    {
        $subject = Stage::subject('thread', '1');

        self::assertSame([], new NoParents()->parentsOf($subject));
        self::assertNull(new OpenFollowing()->approverOf($subject, 'a'));
        self::assertSame(['a', 'b'], new OpenAccess()->filter($subject, ['a', 'b']));
    }
}
