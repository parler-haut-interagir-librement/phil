<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\NavigationLabels;
use Phil\Epub\TocEntry;

/**
 * The navigation document, `nav.xhtml`: the table of contents, the landmarks and the page list.
 *
 * @internal
 */
final class NavigationDocument
{
    /**
     * @param list<TocEntry>                      $toc
     * @param list<array{string, string, string}> $landmarks type, label and address of each landmark
     * @param list<array{string, string}>         $pages     address and label of each page marker
     */
    public static function body(NavigationLabels $labels, array $toc, array $landmarks, array $pages): string
    {
        $xhtml = '<nav epub:type="toc" id="toc" role="doc-toc"><h1>'.XhtmlSerialization::text($labels->contents).'</h1>'.self::entries($toc).'</nav>';

        if ([] !== $landmarks) {
            $items = '';

            foreach ($landmarks as [$type, $label, $href]) {
                $items .= '<li><a epub:type="'.$type.'" href="'.XhtmlSerialization::attribute($href).'">'.XhtmlSerialization::text($label).'</a></li>';
            }

            $xhtml .= "\n".'<nav epub:type="landmarks" id="landmarks" hidden="hidden"><h2>'.XhtmlSerialization::text($labels->landmarks).'</h2><ol>'.$items.'</ol></nav>';
        }

        if ([] !== $pages) {
            $items = '';

            foreach ($pages as [$href, $label]) {
                $items .= '<li><a href="'.XhtmlSerialization::attribute($href).'">'.XhtmlSerialization::text($label).'</a></li>';
            }

            $xhtml .= "\n".'<nav epub:type="page-list" id="page-list" hidden="hidden"><h2>'.XhtmlSerialization::text($labels->pages).'</h2><ol>'.$items.'</ol></nav>';
        }

        return $xhtml;
    }

    /**
     * @param list<TocEntry> $entries
     */
    private static function entries(array $entries): string
    {
        $xhtml = '';

        foreach ($entries as $entry) {
            $xhtml .= '<li><a href="'.XhtmlSerialization::attribute($entry->href).'">'.XhtmlSerialization::text($entry->label).'</a>'
                .([] === $entry->children ? '' : self::entries($entry->children)).'</li>';
        }

        return '<ol>'.$xhtml.'</ol>';
    }
}
