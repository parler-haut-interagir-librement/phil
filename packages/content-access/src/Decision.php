<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use DateTimeImmutable;
use Phil\Rules\Evaluation\Step;

/**
 * What a viewer may see of a content, and why.
 *
 * - `access`: the whole content, its public part, a locked entry, or nothing — then the application answers as it
 *   answers for a content that does not exist, so that the response never tells a hidden content from a missing one.
 * - `reason`: the stable code of the decision.
 * - `personal`: whether the decision depends on who views. A personal decision is never stored in a shared cache.
 * - `changesAt`: the end of an early access still to come, when the decision changes for everyone; a cache keeps
 *   nothing past it.
 * - `explanation`: the leaves of the rule that decided, with their reason codes and details — such as the day a
 *   content opens —, for a locked entry to say what remains. Never shown for a hidden content.
 */
final readonly class Decision
{
    /**
     * @param list<Step> $explanation
     */
    public function __construct(
        public Access $access,
        public Reason $reason,
        public bool $personal,
        public ?DateTimeImmutable $changesAt = null,
        public array $explanation = [],
    ) {
    }

    /**
     * Whether the viewer reads the whole content.
     */
    public function isGranted(): bool
    {
        return Access::Full === $this->access;
    }

    /**
     * Whether the content appears to the viewer at all, in a list or at its address.
     */
    public function isVisible(): bool
    {
        return Access::Hidden !== $this->access;
    }
}
