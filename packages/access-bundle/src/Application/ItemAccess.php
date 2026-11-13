<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\RelationCheck;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\Explanation;
use Phil\ResourcePermissions\ObjectList;
use Phil\ResourcePermissions\Subject;
use Psr\Log\LoggerInterface;

/**
 * The access of the request's subject to items: may it act at a sharing level, why, and which items of a kind it sees.
 *
 * While an admin views the site as a member, only the narrowest level — viewing — may be granted: the view reads and
 * never acts in the member's name. A check past its budget is refused and logged.
 */
final readonly class ItemAccess
{
    public function __construct(
        private RelationCheck $check,
        private AccessSubject $subject,
        private SharingLevels $levels,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function can(string $level, AccessibleItem|ResourceReference $item): bool
    {
        $this->levels->assert($level);

        if ($level !== $this->levels->narrowest() && $this->subject->isViewingAs()) {
            return false;
        }

        return $this->allows($this->subject->subject(), $level, $this->reference($item));
    }

    /**
     * Whether `$subject` — a member, a group's members — may act at `$level` on `$item`, whoever makes the request.
     */
    public function allows(Subject $subject, string $level, AccessibleItem|ResourceReference $item): bool
    {
        $this->levels->assert($level);

        try {
            return $this->check->check($subject, $level, $this->reference($item));
        } catch (TraversalBudgetExceeded $traversalBudgetExceeded) {
            $this->logger?->warning('Access to {item} refused: {reason}', ['item' => $this->reference($item)->toString(), 'reason' => $traversalBudgetExceeded->getMessage()]);

            return false;
        }
    }

    /**
     * The tuples that give the request's subject `$level` on `$item`, or null.
     */
    public function explain(string $level, AccessibleItem|ResourceReference $item): ?Explanation
    {
        try {
            return $this->check->explain($this->subject->subject(), $this->levels->assert($level), $this->reference($item));
        } catch (TraversalBudgetExceeded) {
            return null;
        }
    }

    /**
     * The items of `$kind` the request's subject may act on at `$level` — viewing, by default —, ordered by identifier,
     * at most `$limit`; an empty, incomplete list past the budget.
     */
    public function visible(string $kind, int $limit = 100, ?string $level = null): ObjectList
    {
        $level = $this->levels->assert($level ?? $this->levels->narrowest());

        if ($level !== $this->levels->narrowest() && $this->subject->isViewingAs()) {
            return new ObjectList([], true);
        }

        try {
            return $this->check->objects($this->subject->subject(), $level, $kind, $limit);
        } catch (TraversalBudgetExceeded $traversalBudgetExceeded) {
            $this->logger?->warning('The {kind} items could not be listed: {reason}', ['kind' => $kind, 'reason' => $traversalBudgetExceeded->getMessage()]);

            return new ObjectList([], false);
        }
    }

    private function reference(AccessibleItem|ResourceReference $item): ResourceReference
    {
        return $item instanceof AccessibleItem ? $item->accessReference() : $item;
    }
}
