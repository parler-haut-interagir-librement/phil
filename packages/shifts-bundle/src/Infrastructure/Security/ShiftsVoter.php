<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Security;

use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may do what:
 *
 * - `phil_shifts.volunteer`: sign up, express interest, withdraw, offer and take a swap — a person signed in, with the
 *   role of the configuration when there is one;
 * - `phil_shifts.coordinate`: plan and correct shifts, assign and take off volunteers, allocate, approve and refuse a
 *   swap — a coordinator, with the role `ROLE_SHIFTS_COORDINATOR` unless the configuration names another.
 *
 * @extends Voter<string, mixed>
 */
final class ShiftsVoter extends Voter
{
    public const string VOLUNTEER = 'phil_shifts.volunteer';

    public const string COORDINATE = 'phil_shifts.coordinate';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly ?string $volunteerRole,
        private readonly string $coordinatorRole,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VOLUNTEER === $attribute || self::COORDINATE === $attribute;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ('' === $token->getUserIdentifier()) {
            return false;
        }

        if (self::COORDINATE === $attribute) {
            return $this->decisions->decide($token, [$this->coordinatorRole]);
        }

        return null === $this->volunteerRole || $this->decisions->decide($token, [$this->volunteerRole]);
    }
}
