<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Infrastructure\Store;

use Override;
use Phil\SearchSynonyms\Application\Contract\SynonymStore;
use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsReader;
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsWriter;
use RuntimeException;

/**
 * Synonyms in a directory, one file per language in the Solr synonyms format: `fr.txt`, `en.txt` — files a project
 * keeps under version control, or that the administration writes. Each file is read when asked, so that a worker sees
 * the last one saved; a save writes a file beside it, then moves it in place, so that a reader never sees half a file.
 */
final readonly class FileSynonymStore implements SynonymStore
{
    public const string EXTENSION = '.txt';

    public function __construct(
        private string $directory,
        private SolrSynonymsReader $reader = new SolrSynonymsReader(),
        private SolrSynonymsWriter $writer = new SolrSynonymsWriter(),
    ) {
    }

    /**
     * @throws InvalidSynonyms when the file holds a line that is no rule
     */
    #[Override]
    public function get(string $language): SynonymList
    {
        $language = SynonymList::language($language);
        $file = $this->file($language);

        if (!is_file($file)) {
            return SynonymList::of($language);
        }

        $text = file_get_contents($file, length: SolrSynonymsReader::MAX_BYTES + 1);

        if (false === $text) {
            throw new RuntimeException(\sprintf('The synonyms of "%s" cannot be read from %s.', $language, $file));
        }

        return $this->reader->read($text, $language);
    }

    #[Override]
    public function save(SynonymList $list): void
    {
        if (file_exists($this->directory) ? !is_dir($this->directory) : !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(\sprintf('The directory of the synonyms, %s, cannot be created.', $this->directory));
        }

        $file = $this->file($list->language);

        if (is_dir($file)) {
            throw new RuntimeException(\sprintf('The synonyms of "%s" cannot be written to %s.', $list->language, $file));
        }

        $next = \sprintf('%s.%d.tmp', $file, random_int(0, \PHP_INT_MAX));

        if (false === file_put_contents($next, $this->writer->write($list)) || !rename($next, $file)) {
            throw new RuntimeException(\sprintf('The synonyms of "%s" cannot be written to %s.', $list->language, $file));
        }
    }

    #[Override]
    public function languages(): array
    {
        $languages = [];

        // glob() sorts its result: the names are sorted as the files are.
        $files = glob($this->file('*'));

        foreach (false === $files ? [] : $files as $file) {
            $name = basename($file, self::EXTENSION);

            if (1 === preg_match('/^[a-z]{2,3}$/D', $name)) {
                $languages[] = $name;
            }
        }

        return $languages;
    }

    private function file(string $language): string
    {
        return rtrim($this->directory, '/').'/'.$language.self::EXTENSION;
    }
}
