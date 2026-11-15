<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Override;
use Phil\ModerationBundle\Application\Contract\MemberProfiles;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * The profiles of the members as a target of the moderation, of type `profile`: a profile is reported and decided on
 * like any content, the application's `MemberProfiles` applying the decisions.
 */
final readonly class ProfileTarget implements ModerationTarget
{
    public const string TYPE = 'profile';

    public function __construct(
        private MemberProfiles $profiles,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function describe(string $id): ?TargetView
    {
        try {
            $member = MemberId::of($id);
        } catch (InvalidModeration) {
            return null;
        }

        return $this->profiles->describe($member);
    }

    #[Override]
    public function apply(string $id, DecisionAction $action): void
    {
        $this->profiles->apply(MemberId::of($id), $action);
    }
}
