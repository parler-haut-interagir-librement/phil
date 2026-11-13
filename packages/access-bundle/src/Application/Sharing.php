<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use DateTimeImmutable;
use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Application\View\Share;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Psr\Clock\ClockInterface;

/**
 * Shares an item with a member or an access group at a sharing level, until a date when it is given: the share ends
 * on its own at that second, by the clock, without a job.
 */
final readonly class Sharing
{
    /** The shares read per level. */
    public const int MAX_SHARES = 200;

    public function __construct(
        private RelationWriter $writer,
        private TupleStore $store,
        private SharingLevels $levels,
        private AccessGroups $groups,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Gives `$grantee` the level on `$item`, replacing the end of a share it already had at that level.
     */
    public function share(AccessibleItem|ResourceReference $item, string $level, MemberId|AccessGroup $grantee, ?DateTimeImmutable $until = null): void
    {
        if ($until instanceof DateTimeImmutable && $until->getTimestamp() <= $this->clock->now()->getTimestamp()) {
            throw InvalidAccess::because('A share ends after now.');
        }

        $this->writer->grant($this->reference($item), $this->levels->assert($level), $this->subject($grantee), $until);
    }

    public function unshare(AccessibleItem|ResourceReference $item, string $level, MemberId|AccessGroup $grantee): void
    {
        $this->writer->revoke($this->reference($item), $this->levels->assert($level), $this->subject($grantee));
    }

    /**
     * The shares of an item with members and access groups, level by level, the widest first; the tuples a
     * visibility wrote are not shares.
     *
     * @return list<Share>
     */
    public function shares(AccessibleItem|ResourceReference $item): array
    {
        $shares = [];

        foreach ($this->levels->names as $level) {
            foreach ($this->store->subjects($this->reference($item), $level, self::MAX_SHARES) as $tuple) {
                $subject = $tuple->subject;

                if ($subject->isEvery()) {
                    continue;
                }

                if (MemberId::KIND === $subject->reference->key && !$subject->isSet()) {
                    $shares[] = new Share($level, Share::MEMBER, $subject->reference->id, $tuple->expiresAt);
                } elseif (AccessGroup::KIND === $subject->reference->key && 'member' === $subject->relation) {
                    $shares[] = new Share($level, Share::GROUP, $subject->reference->id, $tuple->expiresAt);
                }
            }
        }

        return $shares;
    }

    private function reference(AccessibleItem|ResourceReference $item): ResourceReference
    {
        return $item instanceof AccessibleItem ? $item->accessReference() : $item;
    }

    private function subject(MemberId|AccessGroup $grantee): Subject
    {
        if ($grantee instanceof MemberId) {
            return Subject::of($grantee->reference());
        }

        return Subject::set(ResourceReference::of(AccessGroup::KIND, $this->groups->group($grantee->name)->name), 'member');
    }
}
