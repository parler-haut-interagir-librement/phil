<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Fixtures;

use Override;
use Phil\ReactionsBundle\Application\ConfiguredVisibility;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Contract\MemberNames;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\Contract\ReactionSource;
use Phil\ReactionsBundle\Application\Contract\ReactionThrottle;
use Phil\ReactionsBundle\Application\Contract\VisibilityPolicy;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Phil\ReactionsBundle\Application\PlainEmojiImages;
use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\ReactionsBundle\Application\ReactionCatalog;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\ReactionsPerMember;
use Phil\ReactionsBundle\Application\ReactionSubject;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionGroup;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionVisibility;
use Phil\ReactionsBundle\Infrastructure\Memory\InMemoryReactionRepository;
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The application services on memory, with test doubles that a test can steer: who is the member, what is refused, how
 * many reactions until the throttle stops, who is named, how many reactions a member gives a content. The list: three quick reactions, one of them private, and two
 * waiting in the picker, one of the site's.
 */
final class Stage implements CurrentMember, ReactionAccess, ReactionThrottle, MemberNames, VisibilityPolicy
{
    public ?string $member = 'ada';

    /** @var list<string> */
    public array $refused = [];

    /** @var list<ReactionSubject> */
    public array $asked = [];

    public bool $tooMany = false;

    /** @var array<string, string> */
    public array $names = [];

    /** @var list<list<string>> */
    public array $namesAsked = [];

    public ReactionVisibility $visibility;

    public CountingRepository $repository;

    public ReactionCatalog $catalog;

    public MockClock $clock;

    /** @var list<object> */
    public array $dispatched = [];

    public Reactions $reactions;

    public ReactionBoard $board;

    public function __construct(int $perMember = 1)
    {
        $this->visibility = new ConfiguredVisibility('everyone', 'everyone', 'everyone')->of(ResourceReference::of('article', '0'));
        $this->repository = new CountingRepository(new InMemoryReactionRepository());
        $this->catalog = new ReactionCatalog([self::unicode(), self::site()], new PlainEmojiImages());
        $this->clock = new MockClock('2026-05-01 10:00:00');
        $this->reactions = new Reactions($this->repository, $this->catalog, $this, $this, $this->clock, new RecordingEvents($this->dispatched), new ReactionsPerMember($perMember));
        $this->board = new ReactionBoard($this->repository, $this->catalog, $this, $this, $this, $this, new IdentityTranslator(), new ReactionsPerMember($perMember));
    }

    public static function unicode(): ReactionSource
    {
        return self::source(new ReactionGroup('unicode', 'source.unicode', [
            ReactionDefinition::unicode('like', 'label.like', '👍'),
            ReactionDefinition::unicode('thanks', 'label.thanks', '🙏'),
            ReactionDefinition::unicode('not_useful', 'label.not_useful', '🫤', isPrivate: true),
            ReactionDefinition::unicode('spark', 'label.spark', '✨', quick: false),
        ]));
    }

    public static function site(): ReactionSource
    {
        return self::source(new ReactionGroup('site', 'source.site', [
            ReactionDefinition::image('gnome', 'label.gnome', '/e/gnome.webp', 'alt.gnome'),
        ]));
    }

    public static function source(ReactionGroup $group): ReactionSource
    {
        return new readonly class($group) implements ReactionSource {
            public function __construct(private ReactionGroup $group)
            {
            }

            #[Override]
            public function group(): ReactionGroup
            {
                return $this->group;
            }
        };
    }

    #[Override]
    public function current(): ?string
    {
        return $this->member;
    }

    #[Override]
    public function canReact(ReactionSubject $subject): bool
    {
        $this->asked[] = $subject;

        return !\in_array($subject->reaction->code, $this->refused, true);
    }

    #[Override]
    public function consume(string $member): void
    {
        if ($this->tooMany) {
            throw new TooManyReactions(12);
        }
    }

    #[Override]
    public function names(array $members): array
    {
        $this->namesAsked[] = $members;

        return array_intersect_key($this->names, array_flip($members));
    }

    #[Override]
    public function of(ResourceReference $content): ReactionVisibility
    {
        return $this->visibility;
    }
}
