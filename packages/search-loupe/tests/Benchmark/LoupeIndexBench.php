<?php

declare(strict_types=1);

namespace Phil\SearchLoupe\Tests\Benchmark;

use FilesystemIterator;
use Generator;
use Loupe\Loupe\LoupeFactory;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchLoupe\LoupeIndex;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every Doctrine flush of an indexed entity reaches the Loupe index, which checks its fields file, widens the Loupe
 * configuration and adds the document; a document that brings a field the index does not know yet opens Loupe anew
 * with the wider configuration: in memory, every document it holds is written again; on disk, they are copied into a
 * new directory.
 *
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 */
final class LoupeIndexBench
{
    private const array WORDS = ['soirée', 'partie', 'table', 'joueurs', 'règles', 'carte', 'dés', 'campagne', 'monde', 'histoire', 'personnage', 'aventure', 'ville', 'groupe', 'samedi', 'club', 'jeux', 'rôle'];

    private LoupeIndex $index;

    private ?string $directory = null;

    private int $flush = 0;

    public function __construct()
    {
        $this->index = new LoupeIndex(new LoupeFactory());
    }

    /**
     * @param array{documents: int, storage: string} $params
     */
    public function setUp(array $params): void
    {
        $this->directory = 'disk' === $params['storage'] ? sys_get_temp_dir().'/phil-search-loupe-bench-'.bin2hex(random_bytes(6)) : null;
        $this->index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $batch = [];

        for ($i = 0; $i < $params['documents']; ++$i) {
            $batch[] = $this->document($i);

            if (100 === \count($batch)) {
                $this->index->index(...$batch);
                $batch = [];
            }
        }

        if ([] !== $batch) {
            $this->index->index(...$batch);
        }

        $this->flush = $params['documents'];
    }

    public function tearDown(): void
    {
        if (null === $this->directory || !is_dir($this->directory)) {
            return;
        }

        /** @var iterable<SplFileInfo> $entries */
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->directory);
    }

    /**
     * One flush of one entity whose fields the index already knows: the per-flush cost.
     *
     * @ParamProviders({"storages", "sizes"})
     *
     * @Revs(20)
     */
    public function benchIndexKnownFields(): void
    {
        $this->index->index($this->document($this->flush++));
    }

    /**
     * One flush of one entity that brings a new filter: Loupe is opened anew and indexes again what it holds.
     *
     * @ParamProviders("widenings")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchIndexNewField(): void
    {
        $id = $this->flush++;
        $this->index->index(Document::of('articles', 'article-'.$id, ['title' => 'Article '.$id], ['locale' => 'fr', 'field'.$id => 'yes']));
    }

    /**
     * @return Generator<string, array{storage: string}>
     */
    public function storages(): Generator
    {
        yield 'disk' => ['storage' => 'disk'];
        yield 'memory' => ['storage' => 'memory'];
    }

    /**
     * @return Generator<string, array{documents: int}>
     */
    public function sizes(): Generator
    {
        yield '100 documents' => ['documents' => 100];
        yield '1k documents' => ['documents' => 1_000];
    }

    /**
     * @return Generator<string, array{storage: string, documents: int}>
     */
    public function widenings(): Generator
    {
        yield 'disk,50 documents' => ['storage' => 'disk', 'documents' => 50];
        yield 'disk,500 documents' => ['storage' => 'disk', 'documents' => 500];
        yield 'memory,50 documents' => ['storage' => 'memory', 'documents' => 50];
        yield 'memory,500 documents' => ['storage' => 'memory', 'documents' => 500];
    }

    private function document(int $i): Document
    {
        $count = \count(self::WORDS);
        $words = [];

        for ($w = 0; $w < 40; ++$w) {
            $words[] = self::WORDS[($i * 7 + $w * 13) % $count];
        }

        return Document::of('articles', 'article-'.$i, ['title' => 'Article '.$i.' '.self::WORDS[$i % $count], 'body' => implode(' ', $words)], ['locale' => 0 === $i % 4 ? 'en' : 'fr', 'tags' => ['jdr', 'tag-'.($i % 20)]]);
    }
}
