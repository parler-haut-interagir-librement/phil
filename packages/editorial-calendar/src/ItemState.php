<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

/**
 * Where a planned item stands, from the idea to its publication or withdrawal.
 */
enum ItemState: string
{
    case Idea = 'idea';
    case Drafting = 'drafting';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    /**
     * Whether the workflow allows going from this state to that one. An item moves forward a step, back to rework, to
     * publication once ready, and to withdrawal from anywhere; a withdrawn item comes back as an idea.
     */
    public function canBecome(self $state): bool
    {
        return \in_array($state, match ($this) {
            self::Idea => [self::Drafting, self::Withdrawn],
            self::Drafting => [self::Idea, self::Ready, self::Withdrawn],
            self::Ready => [self::Drafting, self::Scheduled, self::Published, self::Withdrawn],
            self::Scheduled => [self::Ready, self::Published, self::Withdrawn],
            self::Published => [self::Withdrawn],
            self::Withdrawn => [self::Idea],
        }, true);
    }

    /**
     * Whether an item in this state is still to publish: neither published nor withdrawn.
     */
    public function isPending(): bool
    {
        return self::Published !== $this && self::Withdrawn !== $this;
    }
}
