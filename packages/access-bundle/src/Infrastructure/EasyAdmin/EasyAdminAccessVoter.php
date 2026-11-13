<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Override;
use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Votes on EasyAdmin's `EA_ACCESS_ENTITY` and `EA_EXECUTE_ACTION` for the entities that are `AccessibleItem`s: seeing
 * an entity needs the narrowest level, running an action the level the configuration maps it to — `detail` to `view`,
 * `edit` to `edit`, `delete` to `manage` by default. Other entities, and actions mapped to no level, are left to the
 * other voters.
 *
 * EasyAdmin's own voter grants what no permission restricts; for this voter's refusal to count, the application's
 * access decision manager uses the `unanimous` strategy, or the CRUD controllers name the levels as permissions
 * (`setEntityPermission('access.view')`), which `AccessVoter` decides.
 *
 * @extends Voter<string, mixed>
 */
final class EasyAdminAccessVoter extends Voter
{
    /**
     * @param array<string, string> $actions each EasyAdmin action and the sharing level it needs
     */
    public function __construct(
        private readonly ItemAccess $access,
        private readonly SharingLevels $levels,
        private readonly array $actions,
    ) {
        foreach ($actions as $level) {
            $levels->assert($level);
        }
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return Permission::EA_ACCESS_ENTITY === $attribute || Permission::EA_EXECUTE_ACTION === $attribute;
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== $this->target($attribute, $subject);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $target = $this->target($attribute, $subject);
        \assert(null !== $target);
        [$level, $item] = $target;
        $allowed = $this->access->can($level, $item);
        $vote?->addReason(\sprintf($allowed ? 'The relation "%s" is given.' : 'The relation "%s" is not given.', $level));

        return $allowed;
    }

    /**
     * The level and the item an attribute and its subject ask about, or null when the voter abstains.
     *
     * @return array{string, AccessibleItem}|null
     */
    private function target(string $attribute, mixed $subject): ?array
    {
        if (Permission::EA_ACCESS_ENTITY === $attribute) {
            $item = $subject instanceof EntityDto ? $subject->getInstance() : null;

            return $item instanceof AccessibleItem ? [$this->levels->narrowest(), $item] : null;
        }

        if (Permission::EA_EXECUTE_ACTION !== $attribute || !\is_array($subject)) {
            return null;
        }

        $entity = $subject['entity'] ?? null;
        $item = $entity instanceof EntityDto ? $entity->getInstance() : null;
        $action = $subject['action'] ?? null;
        $name = $action instanceof ActionDto ? $action->getName() : $action;
        $level = \is_string($name) ? $this->actions[$name] ?? null : null;

        return $item instanceof AccessibleItem && null !== $level ? [$level, $item] : null;
    }
}
