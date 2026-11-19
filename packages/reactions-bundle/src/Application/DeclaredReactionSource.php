<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\ReactionSource;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionGroup;

/**
 * Reactions written in the bundle's configuration, a Unicode emoji or an image each.
 */
final readonly class DeclaredReactionSource implements ReactionSource
{
    /**
     * @param list<array{code: string, label: string, emoji?: string, image?: string, alt?: string, private: bool, quick: bool, translation_domain: string}> $reactions
     */
    public function __construct(
        private string $name,
        private string $label,
        private array $reactions,
    ) {
    }

    #[Override]
    public function group(): ReactionGroup
    {
        return new ReactionGroup($this->name, $this->label, array_map(static fn (array $reaction): ReactionDefinition => match (true) {
            isset($reaction['emoji']) => ReactionDefinition::unicode($reaction['code'], $reaction['label'], $reaction['emoji'], $reaction['private'], $reaction['quick'], $reaction['translation_domain']),
            default => ReactionDefinition::image($reaction['code'], $reaction['label'], $reaction['image'] ?? '', $reaction['alt'] ?? '', $reaction['private'], $reaction['quick'], $reaction['translation_domain']),
        }, $this->reactions));
    }
}
