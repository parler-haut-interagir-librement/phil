<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

/**
 * Where the translation of a segment stands.
 *
 * - `untranslated`: no target;
 * - `suggested`: a target proposed — by the memory, a machine or an import — that nobody has accepted yet;
 * - `translated`: a target written or accepted by a translator;
 * - `reviewed`: a translated target checked by a reviewer;
 * - `stale`: a target written for a source that has changed since.
 */
enum SegmentState: string
{
    case Untranslated = 'untranslated';
    case Suggested = 'suggested';
    case Translated = 'translated';
    case Reviewed = 'reviewed';
    case Stale = 'stale';

    /**
     * Whether the target can be published: translated or reviewed.
     */
    public function isUsable(): bool
    {
        return self::Translated === $this || self::Reviewed === $this;
    }

    /**
     * Whether the state holds a target.
     */
    public function hasTarget(): bool
    {
        return self::Untranslated !== $this;
    }
}
