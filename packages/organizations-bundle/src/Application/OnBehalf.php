<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Domain\Event\ActedOnBehalf;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Acting on behalf of an organization — publishing, signing, answering as the organization — while the real author is
 * recorded beside it.
 *
 * `act()` checks that the author holds `organization.act_on_behalf` in an active organization, runs the work with the
 * context readable from `current()`, then dispatches `ActedOnBehalf` with the organization and the real author, for
 * `phil/audit-bundle` or any listener. Nothing is dispatched when the work throws. The context is held for the call
 * only, and `reset()` clears it between the requests of a worker.
 */
final class OnBehalf implements ResetInterface
{
    public const string ACTION_PATTERN = '/^[a-z][a-z0-9_.-]{0,63}$/D';

    /** @var list<OnBehalfContext> */
    private array $stack = [];

    public function __construct(
        private readonly OrganizationAccess $access,
        private readonly ClockInterface $clock,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @template T
     *
     * @param string        $action what the author does, 1 to 64 lowercase letters, digits, "_", "." or "-": `post.publish`
     * @param callable(): T $work
     *
     * @return T
     *
     * @throws InvalidOrganization  when the action is out of shape
     * @throws NotAMember           when the author is not a member
     * @throws OrganizationArchived when the organization is archived
     * @throws PermissionDenied     when the author may not act on behalf of the organization
     */
    public function act(OrganizationId $organizationId, MemberId $author, string $action, callable $work): mixed
    {
        if (1 !== preg_match(self::ACTION_PATTERN, $action)) {
            throw InvalidOrganization::because(\sprintf('An action is 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $action));
        }

        $this->access->require($this->access->organization($organizationId), $author, Permission::ACT_ON_BEHALF);
        $this->stack[] = new OnBehalfContext($organizationId->value, $author->value, $action);

        try {
            $result = $work();
        } finally {
            array_pop($this->stack);
        }

        $this->events?->dispatch(new ActedOnBehalf($organizationId->value, $author->value, $action, $this->clock->now()));

        return $result;
    }

    /**
     * The innermost context, while `act()` runs.
     */
    public function current(): ?OnBehalfContext
    {
        return $this->stack[array_key_last($this->stack) ?? -1] ?? null;
    }

    public function reset(): void
    {
        $this->stack = [];
    }
}
