<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Infrastructure\Format;

use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;

/**
 * Writes synonyms in the Solr synonyms format, one rule per line after a comment naming the language, the characters
 * of the format escaped with a backslash: what `SolrSynonymsReader` reads back as the same rules, and what Solr and
 * the engines that read its format load.
 */
final readonly class SolrSynonymsWriter
{
    public function write(SynonymList $list): string
    {
        $lines = [\sprintf('# Synonyms, language %s', $list->language)];

        foreach ($list->sets as $set) {
            $lines[] = $this->line($set);
        }

        return implode("\n", $lines)."\n";
    }

    private function line(SynonymSet $set): string
    {
        if ($set->equivalent) {
            return $this->side($set->from);
        }

        return $this->side($set->from).' => '.$this->side($set->to);
    }

    /**
     * @param list<string> $phrases
     */
    private function side(array $phrases): string
    {
        return implode(', ', array_map(
            static fn (string $phrase): string => preg_replace('/^#/', '\\#', strtr($phrase, ['\\' => '\\\\', ',' => '\\,', '=>' => '\\=>'])) ?? $phrase,
            $phrases,
        ));
    }
}
