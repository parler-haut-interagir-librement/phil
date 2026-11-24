<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Application\Contract\SubjectParents;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * A subject and its parents, nearest first, as far as the configured depth: the subjects whose follows decide whether
 * an activity reaches a member, the nearest deciding. A parent seen before ends the chain, so that a cycle of the
 * application's parents never loops.
 */
final readonly class SubjectChain
{
    public const int MAX_DEPTH = 16;

    /**
     * @throws InvalidFollow when the depth is out of range
     */
    public function __construct(
        private SubjectParents $parents = new NoParents(),
        private int $depth = 5,
    ) {
        if ($depth < 0 || $depth > self::MAX_DEPTH) {
            throw InvalidFollow::because(\sprintf('The depth of the parents is 0 to %d.', self::MAX_DEPTH));
        }
    }

    /**
     * @return non-empty-list<SubjectReference>
     */
    public function of(SubjectReference $subject): array
    {
        $chain = [$subject];
        $seen = [$subject->key() => true];

        if (0 === $this->depth) {
            return $chain;
        }

        foreach ($this->parents->parentsOf($subject) as $parent) {
            if (isset($seen[$parent->key()])) {
                break;
            }

            $seen[$parent->key()] = true;
            $chain[] = $parent;

            if (\count($chain) > $this->depth) {
                break;
            }
        }

        return $chain;
    }
}
