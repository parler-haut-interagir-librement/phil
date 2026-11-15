<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Security;

use Override;
use Phil\Lti\Message\Launch;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user of a launch when the application has no account of its own for it: named by the hashed issuer, client and
 * subject of the launch, with `ROLE_LTI` and, from the roles of the launch, `ROLE_LTI_INSTRUCTOR` or
 * `ROLE_LTI_LEARNER`. It lives in the session only.
 */
final readonly class LtiUser implements UserInterface
{
    /**
     * @param non-empty-string $identifier
     * @param list<string>     $roles
     */
    public function __construct(
        private string $identifier,
        private array $roles,
    ) {
    }

    public static function fromLaunch(Launch $launch): self
    {
        $roles = ['ROLE_LTI'];

        if ($launch->roles()->isInstructor()) {
            $roles[] = 'ROLE_LTI_INSTRUCTOR';
        }

        if ($launch->roles()->isLearner()) {
            $roles[] = 'ROLE_LTI_LEARNER';
        }

        $identifier = $launch->userKey() ?? 'anonymous-'.sodium_bin2hex(random_bytes(16));

        return new self('' === $identifier ? 'anonymous' : $identifier, $roles);
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}
