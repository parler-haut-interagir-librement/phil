<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Application;

use Override;
use Phil\ModerationBundle\Application\Contract\MemberProfiles;
use Phil\ModerationBundle\Application\Exception\UnknownTarget;
use Phil\ModerationBundle\Application\FirstPostSandbox;
use Phil\ModerationBundle\Application\ModerationTargets;
use Phil\ModerationBundle\Application\Moderator;
use Phil\ModerationBundle\Application\NoCurrentMember;
use Phil\ModerationBundle\Application\NoTrustedHiders;
use Phil\ModerationBundle\Application\NoWordScreen;
use Phil\ModerationBundle\Application\ProfileTarget;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\Role;
use Phil\ModerationBundle\Application\Roles;
use Phil\ModerationBundle\Application\Screening;
use Phil\ModerationBundle\Application\ScreeningVerdict;
use Phil\ModerationBundle\Application\Submission;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Tests\Fixture\FakeTarget;
use Phil\ModerationBundle\Tests\Fixture\Refusals;
use Phil\ModerationBundle\Tests\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TargetView::class)]
#[CoversClass(Role::class)]
#[CoversClass(Roles::class)]
#[CoversClass(Reasons::class)]
#[CoversClass(Screening::class)]
#[CoversClass(Submission::class)]
#[CoversClass(ModerationTargets::class)]
#[CoversClass(UnknownTarget::class)]
#[CoversClass(NoWordScreen::class)]
#[CoversClass(NoTrustedHiders::class)]
#[CoversClass(NoCurrentMember::class)]
#[CoversClass(FirstPostSandbox::class)]
#[CoversClass(ProfileTarget::class)]
#[CoversClass(Moderator::class)]
final class ValuesTest extends TestCase
{
    use Refusals;

    public function testAView(): void
    {
        $view = new TargetView('  A post  ', '  Some text  ', '/posts/1', MemberId::of('m-1'));

        self::assertSame('A post', $view->title);
        self::assertSame('Some text', $view->excerpt);
        self::assertSame('/posts/1', $view->url);
        self::assertSame('m-1', $view->author?->value);
        self::assertNull(new TargetView('T', '   ')->excerpt);
        self::assertNull(new TargetView('T')->excerpt);
        self::assertSame(str_repeat('é', 500), new TargetView('T', str_repeat('é', 500))->excerpt);
        self::assertSame(str_repeat('é', 499).'…', new TargetView('T', str_repeat('é', 501))->excerpt);
        self::assertSame('a'.str_repeat('é', 498).'…', new TargetView('T', 'a'.str_repeat('é', 500))->excerpt);
        self::assertSame(str_repeat('é', 200), new TargetView(str_repeat('é', 200))->title);
        self::assertRefused(InvalidModeration::class, 'A title is not empty.', static fn (): TargetView => new TargetView(' '));
        self::assertRefused(InvalidModeration::class, 'A title has at most 200', static fn (): TargetView => new TargetView(str_repeat('é', 201)));
    }

    public function testTheRoles(): void
    {
        $moderator = new Role('moderator', 'ROLE_MODERATOR');
        $comments = new Role('comment-keeper', 'ROLE_COMMENTS', ['comment', 'reaction']);
        $posts = new Role('post-keeper', 'ROLE_POSTS', ['comment', 'forum.post']);
        $roles = new Roles([$moderator, $comments, new Role('moderator', 'ROLE_OTHER')]);

        self::assertTrue($moderator->covers('anything'));
        self::assertTrue($comments->covers('comment'));
        self::assertFalse($comments->covers('forum.post'));
        self::assertSame(['moderator', 'comment-keeper'], array_map(static fn (Role $role): string => $role->name, $roles->all()));
        self::assertSame('ROLE_OTHER', $roles->get('moderator')?->attribute);
        self::assertNull($roles->get('nobody'));
        self::assertSame([], Roles::typesOf([$comments, $moderator]));
        self::assertSame(['comment', 'reaction', 'forum.post'], Roles::typesOf([$comments, $posts]));
        self::assertSame([], Roles::typesOf([]));
        self::assertRefused(InvalidModeration::class, 'is not a role', static fn (): Role => new Role('Moderator', 'ROLE_X'));
    }

    public function testTheReasons(): void
    {
        self::assertSame(Reasons::DEFAULTS, new Reasons()->all());
        self::assertSame(['spam', 'scam'], new Reasons(['spam', 'spam', 'scam'])->all());
        self::assertTrue(new Reasons()->has('spam'));
        self::assertFalse(new Reasons()->has('first-post'));
        self::assertRefused(InvalidModeration::class, 'is not a reason', static fn (): Reasons => new Reasons(['Spam']));
    }

    public function testTheScreeningAndTheSubmission(): void
    {
        self::assertSame(ScreeningVerdict::Allow, new NoWordScreen()->screen('Hello')->verdict);
        self::assertSame('Hello', new NoWordScreen()->screen('Hello')->text);
        self::assertSame([], Screening::allow('x')->matches);
        self::assertTrue(new Submission(ScreeningVerdict::Allow)->isPublished());
        self::assertTrue(new Submission(ScreeningVerdict::Flag)->isPublished());
        self::assertFalse(new Submission(ScreeningVerdict::Hold)->isPublished());
        self::assertFalse(new Submission(ScreeningVerdict::Block)->isPublished());
        self::assertNull(new Submission(ScreeningVerdict::Allow)->text);
    }

    public function testTheDefaults(): void
    {
        self::assertFalse(new NoTrustedHiders()->canHide(MemberId::of('m-1')));
        self::assertNull(new NoCurrentMember()->member());
        self::assertNull(new NoCurrentMember()->moderator());
    }

    public function testTheTargetsByType(): void
    {
        $posts = new FakeTarget('forum.post');
        $targets = new ModerationTargets([$posts, new FakeTarget('comment')]);

        self::assertSame($posts, $targets->get('forum.post'));
        self::assertTrue($targets->has('comment'));
        self::assertFalse($targets->has('listing'));
        self::assertSame(['forum.post', 'comment'], $targets->types());
        self::assertRefused(UnknownTarget::class, 'No moderation target is registered for the type "listing".', static fn (): mixed => $targets->get('listing'));
        self::assertSame('The target "7" of type "comment" does not exist.', UnknownTarget::missing('comment', '7')->getMessage());
    }

    public function testTheFirstPostSandbox(): void
    {
        $stage = new Stage();
        $sandbox = new FirstPostSandbox($stage->sandboxRepository, ['forum.post']);

        self::assertTrue($sandbox->holds(MemberId::of('m-1'), 'forum.post'));
        self::assertFalse($sandbox->holds(MemberId::of('m-1'), 'comment'));

        $stage->sandboxRepository->clear(MemberId::of('m-1'), $stage->clock->now());

        self::assertFalse($sandbox->holds(MemberId::of('m-1'), 'forum.post'));
    }

    public function testTheProfilesAsATarget(): void
    {
        $profiles = new class implements MemberProfiles {
            /** @var list<array{string, DecisionAction}> */
            public array $applied = [];

            #[Override]
            public function describe(MemberId $member): TargetView
            {
                return new TargetView('Profile of '.$member->value);
            }

            #[Override]
            public function apply(MemberId $member, DecisionAction $action): void
            {
                $this->applied[] = [$member->value, $action];
            }
        };
        $target = new ProfileTarget($profiles);

        self::assertSame('profile', $target->type());
        self::assertSame('Profile of m-1', $target->describe('m-1')?->title);
        self::assertNull($target->describe('someone@example.org'));

        $target->apply('m-1', DecisionAction::Hide);

        self::assertSame([['m-1', DecisionAction::Hide]], $profiles->applied);
    }
}
