<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application\Extraction;

use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * The mention marks of a UJO (Universal JSON Output) document, `<a data-mention="user:42">@Ada</a>`, block by block in
 * reading order: each names its member by reference, and shows a label. The document is read in the canonical form
 * `phil/ujo` writes, so it is decoded or normalized first; a mark whose reference is not one is passed over.
 */
final class UjoMentions
{
    private const string MARK = '~<a\s[^>]*?\bdata-mention="([^"]++)"[^>]*+>([^<]*+(?:<(?!/a>)[^<]*+)*+)</a>~';

    /**
     * @return list<Mention>
     */
    public static function find(Document $document): array
    {
        $mentions = [];

        foreach ($document->walk() as $block) {
            foreach (self::texts($block) as $text) {
                preg_match_all(self::MARK, $text, $marks, \PREG_SET_ORDER);

                foreach ($marks as [, $reference, $label]) {
                    try {
                        $member = ResourceReference::fromString(html_entity_decode($reference, \ENT_QUOTES | \ENT_HTML5));
                    } catch (InvalidResource) {
                        continue;
                    }

                    $mentions[] = Mention::ofMember($member, trim(html_entity_decode(strip_tags($label), \ENT_QUOTES | \ENT_HTML5)));
                }
            }
        }

        return $mentions;
    }

    /**
     * The strings of a block's own data: its nested blocks are reached by the document's walk.
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
