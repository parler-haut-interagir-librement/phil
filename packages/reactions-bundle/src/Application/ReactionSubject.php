<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\Resource\ResourceReference;

/**
 * What a voter is asked about: this member, this content, this reaction.
 */
final readonly class ReactionSubject
{
    public function __construct(
        public ResourceReference $content,
        public string $member,
        public ReactionDefinition $reaction,
    ) {
    }
}
