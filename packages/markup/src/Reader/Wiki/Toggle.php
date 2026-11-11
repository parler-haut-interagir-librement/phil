<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

/**
 * A mark of emphasis that both opens and closes an element — `**` in DokuWiki and Creole, `''` in MediaWiki —, paired
 * once the text around it is read: `true` when it opens its element, `false` when it closes it, `null` when it stays
 * text.
 *
 * @internal
 */
final class Toggle
{
    public ?bool $opens = null;

    public function __construct(
        public readonly string $kind,
        public readonly string $tag,
    ) {
    }

    public function html(): string
    {
        return match ($this->opens) {
            true => '<'.$this->tag.'>',
            false => '</'.$this->tag.'>',
            null => Spans::escape($this->kind),
        };
    }
}
