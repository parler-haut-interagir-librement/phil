<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Twig;

use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\Resource\ResourceReference;
use Twig\Attribute\AsTwigFunction;

/**
 * Lets a template of many contents read their reactions at once, before it draws them.
 */
final readonly class ReactionsTwig
{
    public function __construct(
        private ReactionBoard $board,
    ) {
    }

    /**
     * @param iterable<ResourceReference|string> $contents
     */
    #[AsTwigFunction(name: 'reactions_preload')]
    public function preload(iterable $contents): void
    {
        $this->board->preload($contents);
    }
}
