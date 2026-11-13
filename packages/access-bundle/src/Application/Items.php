<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;

/**
 * The owner, space and visibility of items, written as tuples.
 *
 * A visibility is tuples on the narrowest level: every member and every visitor for `Everyone`, the members of the
 * space for `Members`, its admins for `Admins`, none for `Owner` — the owner holds the widest level, so always sees.
 * A tuple `item#visibility@visibility:<value>` keeps which one it is.
 */
final readonly class Items
{
    public function __construct(
        private RelationWriter $writer,
        private TupleStore $store,
        private SharingLevels $levels,
        private Spaces $spaces,
        private Visibility $default = Visibility::Owner,
    ) {
    }

    /**
     * Gives an item its owner, its space — the site's, unless named — and its visibility, private to its owner unless
     * given.
     */
    public function register(AccessibleItem|ResourceReference $item, MemberId $owner, ?Visibility $visibility = null, ?string $space = null): void
    {
        $item = $this->reference($item);
        $space = $this->spaces->space($space);

        $this->writer->update(
            [...$this->current($item, 'owner'), ...$this->current($item, 'space'), ...$this->visibilityTuples($item, null)],
            [
                new RelationTuple($item, 'owner', Subject::of($owner->reference())),
                new RelationTuple($item, 'space', Subject::of($space)),
                ...$this->visibilityTuples($item, $space, $visibility ?? $this->default),
            ],
        );
    }

    public function changeVisibility(AccessibleItem|ResourceReference $item, Visibility $visibility): void
    {
        $item = $this->reference($item);
        $space = $this->space($item) ?? throw InvalidAccess::because(\sprintf('%s has no space: register it first.', $item->toString()));

        $this->writer->update($this->visibilityTuples($item, null), $this->visibilityTuples($item, $space, $visibility));
    }

    public function visibility(AccessibleItem|ResourceReference $item): ?Visibility
    {
        $marker = $this->store->subjects($this->reference($item), AccessModel::VISIBILITY, 1)[0] ?? null;

        return null === $marker ? null : Visibility::tryFrom($marker->subject->reference->id);
    }

    public function owner(AccessibleItem|ResourceReference $item): ?MemberId
    {
        $owner = $this->store->subjects($this->reference($item), 'owner', 1)[0] ?? null;

        return null === $owner ? null : MemberId::of($owner->subject->reference->id);
    }

    public function transfer(AccessibleItem|ResourceReference $item, MemberId $owner): void
    {
        $item = $this->reference($item);

        $this->writer->update($this->current($item, 'owner'), [new RelationTuple($item, 'owner', Subject::of($owner->reference()))]);
    }

    /**
     * Deletes every tuple of a deleted item: its owner, space, visibility and shares.
     */
    public function forget(AccessibleItem|ResourceReference $item): int
    {
        return $this->writer->forget($this->reference($item));
    }

    private function reference(AccessibleItem|ResourceReference $item): ResourceReference
    {
        return $item instanceof AccessibleItem ? $item->accessReference() : $item;
    }

    private function space(ResourceReference $item): ?ResourceReference
    {
        return ($this->store->subjects($item, 'space', 1)[0] ?? null)?->subject->reference;
    }

    /**
     * @return list<RelationTuple>
     */
    private function current(ResourceReference $item, string $relation): array
    {
        return $this->store->subjects($item, $relation, 10);
    }

    /**
     * The tuples of one visibility, or — without one — every tuple any visibility may have written, to delete.
     *
     * @return list<RelationTuple>
     */
    private function visibilityTuples(ResourceReference $item, ?ResourceReference $space, ?Visibility $visibility = null): array
    {
        $level = $this->levels->narrowest();

        if (!$visibility instanceof Visibility) {
            $written = $this->space($item);
            $subjects = [Subject::every('member'), Subject::every(AccessModel::VISITOR)];

            if ($written instanceof ResourceReference) {
                $subjects[] = Subject::set($written, 'member');
                $subjects[] = Subject::set($written, 'admin');
            }

            return [...$this->current($item, AccessModel::VISIBILITY), ...$this->store->matching($item, [$level], $subjects)];
        }

        \assert($space instanceof ResourceReference);
        $tuples = [new RelationTuple($item, AccessModel::VISIBILITY, Subject::of(ResourceReference::of(AccessModel::VISIBILITY, $visibility->value)))];
        $subjects = match ($visibility) {
            Visibility::Everyone => [Subject::every('member'), Subject::every(AccessModel::VISITOR)],
            Visibility::Members => [Subject::set($space, 'member')],
            Visibility::Admins => [Subject::set($space, 'admin')],
            Visibility::Owner => [],
        };

        foreach ($subjects as $subject) {
            $tuples[] = new RelationTuple($item, $level, $subject);
        }

        return $tuples;
    }
}
