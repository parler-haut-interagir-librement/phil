<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\App;

use Override;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The application's own members: the user identifiers of the test are stable identifiers, which it says by replacing
 * the bundle's `CurrentMember`; `trusted` is trusted to hide.
 */
final readonly class Members implements CurrentMember, TrustedHiders
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Override]
    public function member(): ?MemberId
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof UserInterface ? MemberId::of($user->getUserIdentifier()) : null;
    }

    #[Override]
    public function canHide(MemberId $member): bool
    {
        return 'trusted' === $member->value;
    }
}
