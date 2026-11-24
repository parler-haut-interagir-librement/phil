<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Extraction;

use Dom\HTMLDocument;

/**
 * The targets of the fragments of a page: the `id` of any element and the `name` of an `a` element, as the HTML
 * Standard looks for them. An empty fragment and `top` always point somewhere: the top of the page.
 *
 * A page read only in part — past the bytes allowed, or cut by its server — may hold the element further down: its
 * index then finds every fragment, so that a missing anchor is only reported when the whole page was read.
 *
 * @internal
 */
final readonly class AnchorIndex
{
    /**
     * @param array<string, string> $targets each target, by itself
     */
    private function __construct(
        private array $targets,
        private bool $complete,
    ) {
    }

    public static function fromHtml(string $html, int $maxBytes, bool $complete = true): self
    {
        // A page past the bytes allowed is not parsed: read in part, it would find every fragment anyway.
        if (!$complete || \strlen($html) > $maxBytes) {
            return new self([], false);
        }

        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR, 'UTF-8');
        $targets = [];

        foreach ($document->querySelectorAll('[id], a[name]') as $element) {
            foreach (['id', 'name'] as $attribute) {
                $value = $element->getAttribute($attribute);

                if (null !== $value && ('id' === $attribute || 'a' === $element->localName)) {
                    $targets[$value] = $value;
                }
            }
        }

        return new self($targets, true);
    }

    public function has(string $fragment): bool
    {
        if (!$this->complete || '' === $fragment || 0 === strcasecmp($fragment, 'top')) {
            return true;
        }

        return isset($this->targets[$fragment]) || isset($this->targets[rawurldecode($fragment)]);
    }
}
