<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use DateInterval;
use DateTimeImmutable;
use Phil\AccessBundle\Application\Event\MemberJoinedGroup;
use Phil\AccessBundle\Application\Event\MemberLeftGroup;
use Phil\AccessBundle\Application\View\Membership;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The access groups the site declares, and their members. A member comes in only through an entry point the group
 * accepts; a group with a duration ends each membership on its own that long after it began, and coming in again
 * starts the duration again.
 */
final readonly class AccessGroups
{
    /** @var array<string, AccessGroup> */
    private array $groups;

    /**
     * @param list<AccessGroup> $groups
     */
    public function __construct(
        private RelationWriter $writer,
        private TupleStore $store,
        private ClockInterface $clock,
        array $groups = [],
        private ?EventDispatcherInterface $events = null,
    ) {
        $byName = [];

        foreach ($groups as $group) {
            $byName[$group->name] = $group;
        }

        $this->groups = $byName;
    }

    public function group(string $name): AccessGroup
    {
        return $this->groups[$name] ?? throw InvalidAccess::because(\sprintf('The site declares no access group "%s".', $name));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->groups);
    }

    /**
     * Brings `$member` into `$group` through `$entryPoint`; returns when the membership ends, or null.
     */
    public function join(string $group, MemberId $member, EntryPoint $entryPoint): ?DateTimeImmutable
    {
        $declared = $this->group($group);

        if (!$declared->accepts($entryPoint)) {
            throw InvalidAccess::because(\sprintf('The access group "%s" accepts no member through "%s".', $group, $entryPoint->value));
        }

        $until = $declared->duration instanceof DateInterval ? $this->clock->now()->add($declared->duration) : null;
        $this->writer->grant($this->reference($group), 'member', $member->reference(), $until);
        $this->events?->dispatch(new MemberJoinedGroup($group, $member->value, $entryPoint->value, $until));

        return $until;
    }

    public function leave(string $group, MemberId $member): void
    {
        $this->writer->revoke($this->reference($this->group($group)->name), 'member', $member->reference());
        $this->events?->dispatch(new MemberLeftGroup($group, $member->value));
    }

    /**
     * Brings a new member into every group whose entry points include the default one; returns their names.
     *
     * @return list<string>
     */
    public function joinDefaults(MemberId $member): array
    {
        $joined = [];

        foreach ($this->groups as $name => $group) {
            if ($group->accepts(EntryPoint::Default)) {
                $this->join($name, $member, EntryPoint::Default);
                $joined[] = $name;
            }
        }

        return $joined;
    }

    /**
     * The groups `$member` is in, by name, at most `$limit`.
     *
     * @param positive-int $limit
     *
     * @return list<string>
     */
    public function groupsOf(MemberId $member, int $limit = 100): array
    {
        return array_map(
            static fn (RelationTuple $tuple): string => $tuple->object->id,
            $this->store->objects(AccessGroup::KIND, 'member', Subject::of($member->reference()), $limit),
        );
    }

    /**
     * The members of `$group`, ordered by identifier, at most `$limit`, with when each membership ends.
     *
     * @param positive-int $limit
     *
     * @return list<Membership>
     */
    public function members(string $group, int $limit = 100): array
    {
        return array_map(
            static fn (RelationTuple $tuple): Membership => new Membership(MemberId::of($tuple->subject->reference->id), $tuple->expiresAt),
            $this->store->subjects($this->reference($this->group($group)->name), 'member', $limit),
        );
    }

    private function reference(string $group): ResourceReference
    {
        return ResourceReference::of(AccessGroup::KIND, $group);
    }
}
