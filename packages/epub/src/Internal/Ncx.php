<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\Metadata;
use Phil\Epub\TocEntry;

/**
 * The EPUB 2 table of contents, `toc.ncx`, which older reading systems read instead of the navigation document: the
 * same entries as its table of contents and the same page list. Entries that lead to the same address share their
 * play order, numbered in the order their addresses are first met.
 *
 * @internal
 */
final class Ncx
{
    /** @var array<string, int> */
    private array $order = [];

    private int $point = 0;

    /**
     * @param list<TocEntry>              $toc
     * @param list<array{string, string}> $pages address and label of each page marker
     */
    public static function write(Metadata $metadata, array $toc, array $pages): string
    {
        return new self()->document($metadata, $toc, $pages);
    }

    /**
     * @param list<TocEntry>              $toc
     * @param list<array{string, string}> $pages
     */
    private function document(Metadata $metadata, array $toc, array $pages): string
    {
        $map = $this->points($toc, 2);
        $numbers = array_map(static fn (array $page): int => self::isNumber($page[1]) ? (int) $page[1] : 0, $pages);
        $list = '';

        foreach ($pages as $index => [$href, $label]) {
            [$type, $value] = match (true) {
                self::isNumber($label) && (int) $label > 0 => ['normal', ' value="'.(int) $label.'"'],
                1 === preg_match('/^[ivxlcdm]+$/iD', $label) => ['front', ''],
                default => ['special', ''],
            };
            $list .= '    <pageTarget id="page-'.($index + 1).'" type="'.$type.'"'.$value.' playOrder="'.$this->order($href).'">'
                .'<navLabel><text>'.XhtmlSerialization::text($label).'</text></navLabel><content src="'.XhtmlSerialization::attribute($href).'"/></pageTarget>'."\n";
        }

        return Xml::DECLARATION
            .'<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="'.XhtmlSerialization::attribute($metadata->language).'">'."\n"
            .'  <head>'."\n"
            .'    <meta name="dtb:uid" content="'.XhtmlSerialization::attribute($metadata->identifier->value).'"/>'."\n"
            .'    <meta name="dtb:depth" content="'.self::depth($toc).'"/>'."\n"
            .'    <meta name="dtb:totalPageCount" content="'.\count($pages).'"/>'."\n"
            .'    <meta name="dtb:maxPageNumber" content="'.max([0, ...$numbers]).'"/>'."\n"
            .'  </head>'."\n"
            .'  <docTitle><text>'.XhtmlSerialization::text($metadata->title).'</text></docTitle>'."\n"
            .'  <navMap>'."\n".$map.'  </navMap>'."\n"
            .([] === $pages ? '' : '  <pageList>'."\n".$list.'  </pageList>'."\n")
            .'</ncx>'."\n";
    }

    /**
     * @param list<TocEntry> $entries
     */
    private function points(array $entries, int $indent): string
    {
        $xml = '';
        $space = str_repeat('  ', $indent);

        foreach ($entries as $entry) {
            $xml .= $space.'<navPoint id="point-'.++$this->point.'" playOrder="'.$this->order($entry->href).'">'
                .'<navLabel><text>'.XhtmlSerialization::text($entry->label).'</text></navLabel><content src="'.XhtmlSerialization::attribute($entry->href).'"/>'
                .([] === $entry->children ? '' : "\n".$this->points($entry->children, $indent + 1).$space)
                .'</navPoint>'."\n";
        }

        return $xml;
    }

    private static function isNumber(string $label): bool
    {
        return 1 === preg_match('/^\d+$/D', $label);
    }

    private function order(string $href): int
    {
        return $this->order[$href] ??= \count($this->order) + 1;
    }

    /**
     * @param list<TocEntry> $entries
     */
    private static function depth(array $entries): int
    {
        $depth = 0;

        foreach ($entries as $entry) {
            $depth = max($depth, 1 + self::depth($entry->children));
        }

        return $depth;
    }
}
