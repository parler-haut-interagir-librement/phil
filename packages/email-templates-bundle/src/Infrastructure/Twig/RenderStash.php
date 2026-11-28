<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Phil\EmailTemplatesBundle\Application\Contract\MarkdownRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;

/**
 * What the components of one render share, handed to them in the context under a name no schema may declare: the
 * recipient's segments, the images the template embeds, and, in a Markdown body, the HTML of each component kept aside
 * behind a placeholder of letters and digits, which Markdown leaves alone, then put back once the Markdown is HTML.
 *
 * A stash lives for one render; nothing of it outlives the render.
 */
final class RenderStash
{
    /** The name the stash is given in the context of a render. */
    public const string CONTEXT_NAME = '__phil_email';

    private const string ALONE = '/<p>\s*phil([0-9a-f]{16})o([0-9]{1,6})z\s*<\/p>/';

    private const string INLINE = '/phil([0-9a-f]{16})o([0-9]{1,6})z/';

    public RenderMode $mode = RenderMode::Html;

    /** @var list<string> */
    private array $kept = [];

    private readonly string $nonce;

    public function __construct(
        public readonly RenderContext $context,
        public readonly TemplateDefinition $definition,
        private readonly ?MarkdownRenderer $markdown = null,
    ) {
        $this->nonce = sodium_bin2hex(random_bytes(8));
    }

    /**
     * The HTML of a component as the body takes it: as is in HTML, behind a placeholder in Markdown — a paragraph of its
     * own for a block, as a button or a table, so that Markdown never wraps it in the text around it.
     */
    public function keep(string $html, bool $block = true): string
    {
        if (RenderMode::Markdown !== $this->mode) {
            return $html;
        }

        $this->kept[] = $html;
        $placeholder = 'phil'.$this->nonce.'o'.(\count($this->kept) - 1).'z';

        return $block ? "\n\n".$placeholder."\n\n" : $placeholder;
    }

    /**
     * Markdown written inside a component, turned into HTML, its own components put back.
     */
    public function markdown(string $source): string
    {
        if (RenderMode::Markdown !== $this->mode || !$this->markdown instanceof MarkdownRenderer) {
            return $source;
        }

        return $this->restore($this->markdown->toHtml($source));
    }

    /**
     * The HTML of the components put back in place of their placeholders; a placeholder alone in its paragraph
     * replaces the paragraph.
     */
    public function restore(string $html): string
    {
        $put = fn (array $match): string => $match[1] === $this->nonce && isset($this->kept[(int) $match[2]]) ? $this->kept[(int) $match[2]] : $match[0];
        $html = preg_replace_callback(self::ALONE, $put, $html) ?? $html;

        return preg_replace_callback(self::INLINE, $put, $html) ?? $html;
    }
}
