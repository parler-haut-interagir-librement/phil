<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional\App;

use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `ORDER_CREATE`, granted to every user but those whose permission a test revoked.
 *
 * @extends Voter<string, mixed>
 */
final class OrderVoter extends Voter
{
    public const string CREATE = 'ORDER_CREATE';

    /** @var list<string> */
    public array $revoked = [];

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CREATE === $attribute;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return '' !== $token->getUserIdentifier() && !\in_array($token->getUserIdentifier(), $this->revoked, true);
    }
}
