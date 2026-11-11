<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Ujo\Block;

/**
 * The blocks of one container as they are read: inline content gathers into a paragraph, which a blank line or a
 * block ends. A paragraph loses the white space at its edges, and its other new lines become line breaks.
 *
 * @internal
 */
final class Flow
{
    /** @var list<Block> */
    private array $blocks = [];

    private string $paragraph = '';

    public function __construct(
        private readonly Context $context,
    ) {
    }

    public function inline(string $html): void
    {
        $this->paragraph .= $html;
    }

    public function block(Block ...$blocks): void
    {
        $this->flush();

        foreach ($blocks as $block) {
            $this->blocks[] = $block;
        }
    }

    public function flush(): void
    {
        $html = trim($this->paragraph, " \t\n");
        $this->paragraph = '';

        if ('' !== $html) {
            $this->blocks[] = $this->context->block('paragraph', ['text' => Inline::html(str_replace("\n", '<br>', $html))]);
        }
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        $this->flush();

        return $this->blocks;
    }
}
