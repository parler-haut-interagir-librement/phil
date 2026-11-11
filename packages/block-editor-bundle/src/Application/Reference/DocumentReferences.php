<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Reference;

use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * What a document refers to: the references of its mentions and page links, and the addresses of its files. An
 * application keeps them up to date where it tracks usages — a reference collector of its own, a search index — so
 * that a page linked to, or an image shown, is known to be used.
 *
 * The texts are read in the canonical form `phil/ujo` writes, so a document is normalized first.
 */
final class DocumentReferences
{
    /**
     * The references of the `@` mentions, `kind:identifier`, each once, in the order of the document.
     *
     * @return list<string>
     */
    public static function mentions(Document $document): array
    {
        return self::attribute($document, 'data-mention');
    }

    /**
     * The references of the `[[` page links, `kind:identifier`, each once, in the order of the document.
     *
     * @return list<string>
     */
    public static function links(Document $document): array
    {
        return self::attribute($document, 'data-ref');
    }

    /**
     * The addresses of the files the blocks show — an image's or an attachment's `file`, a gallery's `files` —, each
     * once, in the order of the document.
     *
     * @return list<string>
     */
    public static function files(Document $document): array
    {
        $urls = [];

        foreach ($document->walk() as $block) {
            $data = $block->data;
            $files = \is_array($data['files'] ?? null) ? $data['files'] : [];

            foreach ([$data['file'] ?? null, ...$files] as $file) {
                $url = \is_array($file) ? $file['url'] ?? null : null;

                if (\is_string($url)) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<string>
     */
    private static function attribute(Document $document, string $attribute): array
    {
        $references = [];

        foreach ($document->walk() as $block) {
            foreach (self::texts($block) as $text) {
                preg_match_all('~ '.$attribute.'="([^"]+)"~', $text, $matches);
                array_push($references, ...$matches[1]);
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * The strings of a block's own data: its nested blocks are values of their own, reached by the document's walk.
     *
     * @return list<string>
     */
    private static function texts(Block $block): array
    {
        $texts = [];
        $data = $block->data;
        array_walk_recursive($data, static function (mixed $value) use (&$texts): void {
            if (\is_string($value)) {
                $texts[] = $value;
            }
        });

        return $texts;
    }
}
