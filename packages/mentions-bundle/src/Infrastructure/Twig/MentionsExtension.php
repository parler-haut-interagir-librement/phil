<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Twig;

use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Twig\Attribute\AsTwigFilter;

/**
 * The `mentions` filter: the handles of a text linked to the members' profiles. A plain text is escaped first;
 * HTML already marked safe — Markdown rendered by a filter before it — is read as it is. The filter is declared by its
 * attribute, and the service is loaded only when a template calls it.
 *
 * ```twig
 * {{ comment.body|mentions }}
 * {{ proposal.body|markdown|mentions }}
 * ```
 */
final readonly class MentionsExtension
{
    public function __construct(
        private MentionLinker $linker,
    ) {
    }

    #[AsTwigFilter('mentions', isSafe: ['html'], preEscape: 'html')]
    public function link(string $html): string
    {
        return $this->linker->link($html);
    }
}
