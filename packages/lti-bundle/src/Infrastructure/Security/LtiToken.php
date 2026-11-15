<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Security;

use Override;
use Phil\Lti\Message\Launch;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;
use UnexpectedValueException;

/**
 * The security token of a verified launch: its user, its firewall, and the launch, so that the application reads the
 * context, the roles and the endpoints of the services from `$token->launch`.
 */
final class LtiToken extends AbstractToken
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        UserInterface $user,
        private readonly string $firewallName,
        array $roles,
        public readonly Launch $launch,
    ) {
        parent::__construct($roles);
        $this->setUser($user);
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function __serialize(): array
    {
        return [$this->firewallName, $this->launch, parent::__serialize()];
    }

    /**
     * @param array<mixed> $data
     */
    #[Override]
    public function __unserialize(array $data): void
    {
        [$firewallName, $launch, $parent] = $data;
        $this->firewallName = \is_string($firewallName) ? $firewallName : '';
        $this->launch = $launch instanceof Launch ? $launch : throw new UnexpectedValueException('The token of a launch keeps its launch.');
        parent::__unserialize(\is_array($parent) ? $parent : []);
    }
}
