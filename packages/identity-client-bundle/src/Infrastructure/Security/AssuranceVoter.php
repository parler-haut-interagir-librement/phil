<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Override;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Grants `OIDC_ASSURANCE:<level>` to a sign-in that satisfies the level, and otherwise asks the firewall to
 * authenticate the user again.
 *
 * @extends Voter<string, mixed>
 */
final class AssuranceVoter extends Voter
{
    public const string PREFIX = 'OIDC_ASSURANCE:';

    /** Tells the authorization request of this very response which level to ask for. */
    public const string REQUEST_ATTRIBUTE = '_phil_identity_client_assurance';

    public function __construct(
        private readonly AssuranceLevels $levels,
        private readonly AssuranceEvaluator $evaluator,
        private readonly ElevationAttempts $attempts,
        private readonly RequestStack $requests,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::PREFIX);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $level = $this->levels->get(substr($attribute, \strlen(self::PREFIX)));
        if (!$level instanceof AssuranceLevel || !$token->getUser() instanceof UserInterface) {
            return false;
        }

        if ($this->evaluator->isSatisfied($level, $token)) {
            return true;
        }

        $request = $this->requests->getCurrentRequest();
        if ($request instanceof Request && $request->hasSession() && $this->attempts->mayRetry($request->getSession(), $level->name)) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $level->name);
            $vote?->requestReAuthentication($attribute);
        }

        $vote?->addReason(\sprintf('The authentication does not satisfy the "%s" assurance level.', $level->name));

        return false;
    }
}
