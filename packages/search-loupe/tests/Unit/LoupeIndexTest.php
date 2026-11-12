<?php

declare(strict_types=1);

namespace Phil\SearchLoupe\Tests\Unit;

use ErrorException;
use FilesystemIterator;
use Loupe\Loupe\LoupeFactory;
use Override;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchLoupe\LoupeIndex;
use Phil\SearchLoupe\Tests\Unit\Fixtures\FailingLoupeFactory;
use Phil\SearchLoupe\Tests\Unit\Fixtures\RecordingLoupeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

#[CoversClass(LoupeIndex::class)]
final class LoupeIndexTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-search-loupe-'.bin2hex(random_bytes(6));

        // The index raises no warning: one not silenced fails the test.
        set_error_handler(static function (int $level, string $message): bool {
            if (0 === (error_reporting() & $level)) {
                return false;
            }

            throw new ErrorException($message, 0, $level);
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();

        if (is_file($this->directory)) {
            unlink($this->directory);
        }

        if (!is_dir($this->directory)) {
            return;
        }

        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach (array_keys(iterator_to_array($paths)) as $path) {
            is_dir((string) $path) ? rmdir((string) $path) : unlink((string) $path);
        }

        rmdir($this->directory);
    }

    public function testDocumentsAreFoundByEveryWordOfTheirTextAndEveryFilter(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->index(
            Document::of('articles', 'a', ['title' => 'Jeux de RÔLE', 'body' => 'Une soirée'], ['locale' => 'fr', 'tags' => ['jdr', 'soirée']]),
            Document::of('articles', 'b', ['title' => 'Role games'], ['locale' => 'en', 'tags' => 'jdr']),
            Document::of('articles', 'c', ['title' => '100% jeux_vidéo !'], ['locale' => 'fr', 'year' => 2026, 'online' => true]),
            Document::of('pages', 'a', ['title' => 'Jeux'], ['locale' => 'fr']),
        );

        self::assertSame([['a', 'c'], 2], $this->found($index, 'articles', SearchQuery::of('JEUX')));
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('jeux rôle')), 'every word');
        self::assertSame([['a', 'b'], 2], $this->found($index, 'articles', SearchQuery::of('', ['tags' => 'jdr'])), 'one value of a list');
        self::assertSame([['a', 'c'], 2], $this->found($index, 'articles', SearchQuery::of('', ['locale' => 'fr'])));
        self::assertSame([['c'], 1], $this->found($index, 'articles', SearchQuery::of('', ['year' => 2026, 'online' => true])), 'compared as text');
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('', ['locale' => 'fr', 'tags' => 'none'])));
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('', ['locale' => "l'autre"])));
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('fr')), 'the filters are not searched');
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('', ['unknown' => 'x'])), 'a field no document filters on');
        self::assertSame([['b'], 3], $this->found($index, 'articles', SearchQuery::of('', [], 1, 1)));
        self::assertSame([['a'], 1], $this->found($index, 'pages', SearchQuery::of('jeux')));
        self::assertSame([[], 0], $this->found($index, 'events', SearchQuery::of('jeux')), 'an index never written');

        $index->index(Document::of('articles', 'd', ['title' => 'Dés', 'summary' => 'Hasard'], ['genre' => 'chance']));
        self::assertSame([['d'], 1], $this->found($index, 'articles', SearchQuery::of('hasard', ['genre' => 'chance'])), 'new fields, the index rebuilt');
        self::assertSame([['a', 'c'], 2], $this->found($index, 'articles', SearchQuery::of('jeux')));
        $kept = $this->kept('articles');
        self::assertSame(['searchable', 'filterable', 'data'], array_keys($kept));
        self::assertSame([['title', 'body', 'summary'], ['genre', 'locale', 'online', 'tags', 'year']], [$kept['searchable'], $kept['filterable']]);
        self::assertIsString($kept['data']);
        self::assertMatchesRegularExpression('/^loupe-[0-9a-f]{12}$/D', $kept['data'], 'a new field moves the index to a new directory');
        self::assertSame([$kept['data'] => true, 'phil_search.json' => false, 'phil_search.lock' => false], $this->entries('articles'));

        $index->index(Document::of('articles', 'b', ['title' => 'Échecs'], ['locale' => 'en']));
        self::assertSame([['b'], 1], $this->found($index, 'articles', SearchQuery::of('échecs')), 'replaced');
        self::assertSame([['a', 'c'], 2], $this->found($index, 'articles', SearchQuery::of('jeux')), 'the same fields, the same index');
    }

    public function testListsOfValuesAndFacetsCountedWithoutTheirOwnFilter(): void
    {
        $index = new LoupeIndex(new LoupeFactory());
        $index->index(
            Document::of('games', 'a', ['title' => 'Jeu de rôle'], ['genre' => ['jdr', 'fantasy'], 'locale' => 'fr']),
            Document::of('games', 'b', ['title' => 'Jeu de plateau'], ['genre' => 'plateau', 'locale' => 'fr']),
            Document::of('games', 'c', ['title' => 'Jeu de cartes'], ['genre' => "l'as", 'locale' => 'en']),
        );

        $result = $index->search('games', SearchQuery::of('jeu', ['genre' => ['jdr', "l'as"], 'locale' => 'fr'], facets: ['genre', 'locale', 'unknown']));

        self::assertSame([['a'], 1], [$result->ids, $result->total]);
        self::assertSame(['fantasy' => 1, 'jdr' => 1, 'plateau' => 1], $this->counts($result, 'genre'));
        self::assertSame(['en' => 1, 'fr' => 1], $this->counts($result, 'locale'));
        self::assertSame([], $result->facet('unknown'));
        self::assertSame(['genre', 'locale', 'unknown'], array_keys($result->facets));

        $plain = $index->search('games', SearchQuery::of('', facets: ['locale']));
        self::assertSame([3, ['fr' => 2, 'en' => 1]], [$plain->total, $this->counts($plain, 'locale')]);
        self::assertSame([], $index->search('games', SearchQuery::of('', ['missing' => 'x'], facets: ['locale']))->facet('locale'));
        self::assertSame(['locale'], array_keys($index->search('games', SearchQuery::of('', ['missing' => 'x'], facets: ['locale']))->facets));
    }

    /**
     * @return array<string, int>
     */
    private function counts(SearchResult $result, string $facet): array
    {
        $counts = [];

        foreach ($result->facet($facet) as $value) {
            $counts[$value->value] = $value->count;
        }

        return $counts;
    }

    public function testAnotherProcessReadsTheFieldsKeptBesideTheIndex(): void
    {
        new LoupeIndex(new LoupeFactory(), $this->directory)->index(
            Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']),
            Document::of('articles', 'b', ['title' => 'Dés'], ['locale' => 'en']),
            Document::of('pages', 'a', ['title' => 'Accueil']),
        );
        $again = new LoupeIndex(new LoupeFactory(), $this->directory);

        self::assertSame([['a'], 1], $this->found($again, 'articles', SearchQuery::of('', ['locale' => 'fr'])));
        $again->remove('articles', ['a']);
        self::assertSame([['b'], 1], $this->found($again, 'articles', SearchQuery::of('')));
        $again->clear('pages');
        self::assertSame([[], 0], $this->found($again, 'pages', SearchQuery::of('')));
        self::assertSame([['b'], 1], $this->found($again, 'articles', SearchQuery::of('')));

        $again->index(Document::of('articles', 'c', ['summary' => 'Hasard'], ['genre' => 'chance']));
        self::assertSame([['b'], 1], $this->found($again, 'articles', SearchQuery::of('dés', ['locale' => 'en'])), 'the fields kept, widened');
        self::assertSame([['c'], 1], $this->found($again, 'articles', SearchQuery::of('hasard', ['genre' => 'chance'])));
    }

    public function testFieldsKeptAmissAreReadAsNone(): void
    {
        mkdir($this->directory.'/articles', 0o777, true);
        file_put_contents($this->directory.'/articles/phil_search.json', '{"searchable": "title", "filterable": ["locale", 3]}');
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']));

        self::assertSame(['searchable' => ['title'], 'filterable' => ['locale'], 'data' => 'loupe'], json_decode((string) file_get_contents($this->directory.'/articles/phil_search.json'), true));

        file_put_contents($this->directory.'/articles/phil_search.json', 'null');
        self::assertSame([['a'], 1], $this->found(new LoupeIndex(new LoupeFactory(), $this->directory), 'articles', SearchQuery::of('jeux')));
    }

    public function testAMatchInTheFirstFieldOutranksOneInTheNext(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->index(
            Document::of('articles', 'in-body', ['title' => 'Une soirée', 'body' => 'Des dragons']),
            Document::of('articles', 'in-title', ['title' => 'Des dragons', 'body' => 'Une soirée']),
        );

        self::assertSame(['in-title', 'in-body'], $index->search('articles', SearchQuery::of('dragons'))->ids);
        self::assertSame(['in-body', 'in-title'], $index->search('articles', SearchQuery::of('soirée'))->ids);
    }

    public function testAProcessSeesTheFieldsAnotherOneLearned(): void
    {
        $worker = new LoupeIndex(new LoupeFactory(), $this->directory);
        $worker->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']));
        self::assertSame([['a'], 1], $this->found($worker, 'articles', SearchQuery::of('', ['locale' => 'fr'])));

        new LoupeIndex(new LoupeFactory(), $this->directory)->index(Document::of('articles', 'b', ['title' => 'Dés'], ['genre' => 'chance']));

        self::assertSame([['b'], 1], $this->found($worker, 'articles', SearchQuery::of('', ['genre' => 'chance'])), 'the new field seen');
        $worker->index(Document::of('articles', 'c', ['title' => 'Cartes'], ['locale' => 'en']));
        self::assertSame(['title'], $this->kept('articles')['searchable']);
        self::assertSame(['genre', 'locale'], $this->kept('articles')['filterable'], 'not written back narrower');
        self::assertSame([['b'], 1], $this->found(new LoupeIndex(new LoupeFactory(), $this->directory), 'articles', SearchQuery::of('', ['genre' => 'chance'])));
    }

    public function testARebuildFillsAnotherDirectoryThenSwitchesToIt(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->index(Document::of('articles', 'old', ['title' => 'Ancien'], ['locale' => 'fr']));

        $reader = new LoupeIndex(new LoupeFactory(), $this->directory);
        self::assertSame([['old'], 1], $this->found($reader, 'articles', SearchQuery::of('')));
        $seen = [];

        $index->rebuild('articles', (function () use ($reader, &$seen): iterable {
            yield [Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr'])];
            $seen[] = $this->found($reader, 'articles', SearchQuery::of(''));

            yield [Document::of('articles', 'b', ['summary' => 'Hasard'], ['genre' => 'chance'])];
        })());

        self::assertSame([[['old'], 1]], $seen, 'the live index answers during the rebuild');
        $kept = $this->kept('articles');
        $data = $kept['data'] ?? null;
        self::assertIsString($data);
        self::assertSame([['title', 'summary'], ['genre', 'locale']], [$kept['searchable'], $kept['filterable']]);
        self::assertMatchesRegularExpression('/^loupe-[0-9a-f]{12}$/D', $data);
        self::assertDirectoryDoesNotExist($this->directory.'/articles/loupe');
        self::assertSame([['a', 'b'], 2], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([['a', 'b'], 2], $this->found($reader, 'articles', SearchQuery::of('')), 'another process opens the new directory');
        self::assertSame([['b'], 1], $this->found($reader, 'articles', SearchQuery::of('hasard', ['genre' => 'chance'])));

        try {
            $index->rebuild('articles', (static function (): iterable {
                yield [Document::of('articles', 'z', ['title' => 'Z'])];

                throw new RuntimeException('The provider failed.');
            })());
            self::fail('The rebuild went on.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The provider failed.', $runtimeException->getMessage());
        }

        self::assertSame([['a', 'b'], 2], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([$data => true, 'phil_search.json' => false, 'phil_search.lock' => false], $this->entries('articles'));

        $index->rebuild('pages', []);
        self::assertSame([[], 0], $this->found($index, 'pages', SearchQuery::of('')));
    }

    public function testARebuildFillsAnIndexNeverWritten(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->rebuild('pages', [[Document::of('pages', 'a', ['title' => 'Accueil'], ['locale' => 'fr'])]]);

        self::assertSame([['a'], 1], $this->found($index, 'pages', SearchQuery::of('accueil', ['locale' => 'fr'])));
    }

    public function testARebuildKeepsTheFieldsAnotherProcessLearnedMeanwhile(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->index(Document::of('articles', 'old', ['title' => 'Ancien'], ['locale' => 'fr']));

        $other = new LoupeIndex(new LoupeFactory(), $this->directory);

        $index->rebuild('articles', (static function () use ($other): iterable {
            yield [Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr'])];
            $other->index(Document::of('articles', 'x', ['title' => 'X'], ['genre' => 'chance']));
        })());
        $index->index(Document::of('articles', 'z', ['title' => 'Z'], ['genre' => 'chance']));

        self::assertSame(['genre', 'locale'], $this->kept('articles')['filterable']);
        self::assertSame([['z'], 1], $this->found($index, 'articles', SearchQuery::of('', ['genre' => 'chance'])), 'the rebuilt index filters on the field');

        $index->rebuild('articles', (static function () use ($other): iterable {
            $other->index(Document::of('articles', 'y', ['title' => 'Y'], ['year' => '2026']));

            yield from [];
        })());

        self::assertSame(['genre', 'locale', 'year'], $this->kept('articles')['filterable']);
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('', ['year' => '2026'])), 'rebuilt from nothing');
    }

    public function testAProcessSeesTheRebuildOfAnother(): void
    {
        $writer = new LoupeIndex(new LoupeFactory(), $this->directory);
        $writer->index(Document::of('articles', 'old', ['title' => 'Ancien'], ['locale' => 'fr']));
        self::assertSame([['old'], 1], $this->found($writer, 'articles', SearchQuery::of('')));

        new LoupeIndex(new LoupeFactory(), $this->directory)->rebuild('articles', [[Document::of('articles', 'new', ['title' => 'Nouveau'], ['locale' => 'fr'])]]);

        self::assertSame([['new'], 1], $this->found($writer, 'articles', SearchQuery::of('')));
    }

    public function testAProcessSeesASearchedFieldAnotherOneLearned(): void
    {
        $worker = new LoupeIndex(new LoupeFactory(), $this->directory);
        $worker->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']));
        self::assertSame([['a'], 1], $this->found($worker, 'articles', SearchQuery::of('jeux')));

        new LoupeIndex(new LoupeFactory(), $this->directory)->index(Document::of('articles', 'b', ['title' => 'Dés', 'summary' => 'Hasard'], ['locale' => 'en']));

        self::assertSame([['b'], 1], $this->found($worker, 'articles', SearchQuery::of('hasard')));
    }

    public function testANewFieldMovesAnIndexOfManyDocumentsToANewDirectory(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $documents = [];

        for ($i = 0; $i < 150; ++$i) {
            $documents[] = Document::of('articles', \sprintf('a%03d', $i), ['title' => 'Jeux '.$i], ['locale' => 0 === $i % 3 ? 'en' : 'fr']);
        }

        $index->index(...$documents);
        $reader = new LoupeIndex(new LoupeFactory(), $this->directory);
        self::assertSame(150, $reader->search('articles', SearchQuery::of(''))->total);
        self::assertSame('loupe', $this->kept('articles')['data']);

        // Loupe 1.1.6 migrating its own files fails here, past a hundred documents: "database table is locked".
        $index->index(Document::of('articles', 'new', ['title' => 'Dés', 'summary' => 'Hasard'], ['locale' => 'fr', 'genre' => 'chance']));

        $kept = $this->kept('articles');
        self::assertSame([['title', 'summary'], ['genre', 'locale']], [$kept['searchable'], $kept['filterable']]);
        self::assertIsString($kept['data']);
        self::assertMatchesRegularExpression('/^loupe-[0-9a-f]{12}$/D', $kept['data']);
        self::assertSame([$kept['data'] => true, 'phil_search.json' => false, 'phil_search.lock' => false], $this->entries('articles'), 'the old directory deleted');

        foreach ([$index, $reader, new LoupeIndex(new LoupeFactory(), $this->directory)] as $each) {
            self::assertSame(151, $each->search('articles', SearchQuery::of(''))->total, 'every document copied');
            self::assertSame(50, $each->search('articles', SearchQuery::of('', ['locale' => 'en']))->total, 'with its filters');
            self::assertSame(['a149'], $each->search('articles', SearchQuery::of('149'))->ids, 'and its text');
            self::assertSame([['new'], 1], $this->found($each, 'articles', SearchQuery::of('hasard', ['genre' => 'chance'])));
        }

        $index->index(Document::of('articles', 'a000', ['title' => 'Cartes'], ['locale' => 'en']));
        self::assertSame([['a000'], 1], $this->found($reader, 'articles', SearchQuery::of('cartes')), 'written to the new directory');
        self::assertSame($kept, $this->kept('articles'), 'the same fields, the same directory');
    }

    public function testACopyIntoANewDirectoryPagesThroughTheDocuments(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $documents = [];

        for ($i = 0; $i < 1001; ++$i) {
            $documents[] = Document::of('articles', \sprintf('a%04d', $i), ['title' => 1000 === $i ? 'Dernier' : 'T']);
        }

        $index->index(...$documents);
        $index->index(Document::of('articles', 'new', ['title' => 'T'], ['genre' => 'chance']));

        self::assertSame(['a1000'], $index->search('articles', SearchQuery::of('dernier'))->ids, 'the second page copied');
        self::assertSame(['new'], $index->search('articles', SearchQuery::of('', ['genre' => 'chance']))->ids);
    }

    public function testACopyThatFailsLeavesTheIndexAsItWas(): void
    {
        $factory = new FailingLoupeFactory();
        $index = new LoupeIndex($factory, $this->directory);
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']));

        $kept = $this->kept('articles');
        $factory->failing = true;

        try {
            $index->index(Document::of('articles', 'b', ['title' => 'Dés'], ['genre' => 'chance']));
            self::fail('The copy went on.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The new directory failed.', $runtimeException->getMessage());
        }

        self::assertSame($kept, $this->kept('articles'));
        self::assertSame(['loupe' => true, 'phil_search.json' => false, 'phil_search.lock' => false], $this->entries('articles'), 'the new directory deleted');
        self::assertSame([['a'], 1], $this->found(new LoupeIndex(new LoupeFactory(), $this->directory), 'articles', SearchQuery::of('jeux', ['locale' => 'fr'])));
    }

    public function testTheFieldsKeptAreReadAgainOnlyWhenTheirFileChanged(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->rebuild('articles', [[Document::of('articles', 'a', ['title' => 'Jeux'])]]);

        $file = $this->directory.'/articles/phil_search.json';
        $live = (string) file_get_contents($file);
        $elsewhere = (string) preg_replace('/loupe-[0-9a-f]{12}/', 'loupe-000000000000', $live);
        $old = time() - 100;

        touch($file, $old);
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('')));

        file_put_contents($file, $elsewhere);
        touch($file, $old);
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('')), 'the same inode, size and time: kept');
        self::assertSame([[], 0], $this->found(new LoupeIndex(new LoupeFactory(), $this->directory), 'articles', SearchQuery::of('')), 'another process reads the file');

        $restore = function () use ($index, $file, $live, $old): void {
            file_put_contents($file, $live);
            touch($file, $old);
            self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('')));
        };

        file_put_contents($file, $elsewhere);
        touch($file, $old - 1);
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('')), 'another time');
        $restore();

        file_put_contents($file, $elsewhere.' ');
        touch($file, $old);
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('')), 'another size');
        $restore();

        file_put_contents($file.'.other', $elsewhere);
        touch($file.'.other', $old);
        rename($file.'.other', $file);
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('')), 'another inode');

        // A time not yet past is never trusted: the file may change again within the same second.
        file_put_contents($file, $live);
        touch($file, time() + 100);
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('')));
        file_put_contents($file, $elsewhere);
        touch($file, time() + 100);
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('')), 'read again');
    }

    #[RequiresPhpExtension('pcntl')]
    public function testAWriteWaitsForAChangeOfDirectoryAnotherProcessMakes(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux']));

        $ready = $this->directory.'/ready';
        $pid = pcntl_fork();

        if (0 === $pid) {
            $lock = fopen($this->directory.'/articles/phil_search.lock', 'c');
            \assert(false !== $lock);
            flock($lock, \LOCK_EX);
            touch($ready);
            // The child ends on the alarm a second later, running nothing of the test's, and its lock goes with it.
            pcntl_signal(\SIGALRM, \SIG_DFL);
            pcntl_alarm(1);
            sleep(10);

            exit(1);
        }

        while (!is_file($ready)) {
            usleep(1_000);
        }

        $start = hrtime(true);
        $index->index(Document::of('articles', 'b', ['title' => 'Dés']));
        $index->remove('articles', ['a']);

        $waited = hrtime(true) - $start;
        pcntl_waitpid($pid, $status);

        self::assertGreaterThan(500_000_000, $waited, 'the write waited for the exclusive lock');
        self::assertSame([['b'], 1], $this->found($index, 'articles', SearchQuery::of('')));
    }

    public function testAnIndexIsOpenedOnceWhileNothingChanges(): void
    {
        $factory = new RecordingLoupeFactory();
        $index = new LoupeIndex($factory, $this->directory);
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']));
        $index->index(Document::of('articles', 'b', ['title' => 'Dés'], ['locale' => 'en']));
        $index->remove('articles', ['b']);
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('jeux', ['locale' => 'fr'])));
        $opened = $factory->opened();
        self::assertCount(1, $opened);

        $index->rebuild('articles', [[Document::of('articles', 'c', ['title' => 'Cartes'], ['locale' => 'fr'])], [Document::of('articles', 'd', ['title' => 'Dés'])]]);
        self::assertSame([['c', 'd'], 2], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertCount(2, $factory->opened(), 'the rebuild opens its own directory once');

        $memory = new RecordingLoupeFactory();
        $inMemory = new LoupeIndex($memory);
        $inMemory->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']));
        $inMemory->index(Document::of('articles', 'b', ['title' => 'Dés'], ['locale' => 'en']));
        self::assertSame([['a', 'b'], 2], $this->found($inMemory, 'articles', SearchQuery::of('')));
        self::assertCount(1, $memory->opened());
    }

    public function testARebuildSwitchesDirectoriesUnderTheLock(): void
    {
        $factory = new RecordingLoupeFactory();
        $index = new LoupeIndex($factory, $this->directory);
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux']));
        $index->rebuild('articles', []);

        self::assertSame([false, true], $factory->opened());
    }

    public function testTheDataDirectoryKeptIsOneTheIndexNamed(): void
    {
        mkdir($this->directory.'/articles', 0o777, true);

        foreach (['../loupe', 'loupe/..', "loupe\n", 'loupe-0123456789ab/..'] as $data) {
            file_put_contents($this->directory.'/articles/phil_search.json', json_encode(['searchable' => [], 'filterable' => [], 'data' => $data], \JSON_THROW_ON_ERROR));
            new LoupeIndex(new LoupeFactory(), $this->directory)->index(Document::of('articles', 'a', ['title' => 'Jeux']));
            $kept = $this->kept('articles')['data'];

            // The first time, the files go to "loupe"; the fields kept read as none, a new field moves them on.
            self::assertIsString($kept);
            self::assertMatchesRegularExpression('/^loupe(-[0-9a-f]{12})?$/D', $kept, json_encode($data, \JSON_THROW_ON_ERROR));
            self::assertDirectoryExists($this->directory.'/articles/'.$kept);
            self::assertSame(['articles'], false === scandir($this->directory) ? [] : array_values(array_diff(scandir($this->directory), ['.', '..'])));
        }
    }

    public function testTheDirectoryOfAnIndexTakesTheDefaultPermissions(): void
    {
        new LoupeIndex(new LoupeFactory(), $this->directory)->index(Document::of('articles', 'a', ['title' => 'Jeux']));

        self::assertSame(0o777 & ~umask(), fileperms($this->directory.'/articles') & 0o777);
    }

    public function testADirectoryThatCannotBeCreatedIsReported(): void
    {
        file_put_contents($this->directory, '');

        $this->expectException(InvalidSearch::class);
        $this->expectExceptionMessageIsOrContains('The directory of the index "articles" cannot be created.');

        new LoupeIndex(new LoupeFactory(), $this->directory)->index(Document::of('articles', 'a', ['title' => 'Jeux']));
    }

    public function testAnIndexNameIsChecked(): void
    {
        $index = new LoupeIndex(new LoupeFactory(), $this->directory);

        foreach ([
            static fn (): SearchResult => $index->search('../../public/x', SearchQuery::of('')),
            static fn () => $index->remove('a/b', ['x']),
            static fn () => $index->clear('A'),
            static fn () => $index->rebuild('..', []),
        ] as $call) {
            try {
                $call();
                self::fail('An index name was not checked.');
            } catch (InvalidSearch $invalidSearch) {
                self::assertStringStartsWith('An index is 1 to 64 lower-case letters', $invalidSearch->getMessage());
            }
        }

        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testInMemoryTheDocumentsOutliveNewFieldsAndRebuilds(): void
    {
        $index = new LoupeIndex(new LoupeFactory());
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']), Document::of('articles', 'b', ['title' => 'Dés']));
        $index->index(Document::of('articles', 'c', ['summary' => 'Hasard'], ['genre' => 'chance']));
        self::assertSame([['a', 'b', 'c'], 3], $this->found($index, 'articles', SearchQuery::of('')));
        $index->remove('articles', ['b']);
        $index->index(Document::of('articles', 'd', ['body' => 'Cartes']));
        self::assertSame([['a', 'c', 'd'], 3], $this->found($index, 'articles', SearchQuery::of('')));

        $index->rebuild('articles', [[Document::of('articles', 'x', ['title' => 'X'], ['locale' => 'en'])], [Document::of('articles', 'y', ['note' => 'Y'])]]);
        self::assertSame([['x', 'y'], 2], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([['x'], 1], $this->found($index, 'articles', SearchQuery::of('', ['locale' => 'en'])));
        $index->index(Document::of('articles', 'z', ['other' => 'Z']));
        self::assertSame([['x', 'y', 'z'], 3], $this->found($index, 'articles', SearchQuery::of('')));
        $index->clear('articles');
        $index->index(Document::of('articles', 'w', ['again' => 'W']));
        self::assertSame([['w'], 1], $this->found($index, 'articles', SearchQuery::of('')));
    }

    /**
     * @return array<string, mixed>
     */
    private function kept(string $index): array
    {
        $kept = json_decode((string) file_get_contents($this->directory.'/'.$index.'/phil_search.json'), true);
        self::assertIsArray($kept);

        /** @var array<string, mixed> $kept */
        return $kept;
    }

    /**
     * @return array<string, bool> each entry of an index's directory, and whether it is a directory
     */
    private function entries(string $index): array
    {
        $entries = [];

        foreach (new FilesystemIterator($this->directory.'/'.$index) as $entry) {
            self::assertInstanceOf(SplFileInfo::class, $entry);
            $entries[$entry->getFilename()] = $entry->isDir();
        }

        ksort($entries);

        return $entries;
    }

    public function testWithoutADirectoryTheIndexesAreInMemory(): void
    {
        $index = new LoupeIndex(new LoupeFactory());
        $index->index(Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr']), Document::of('articles', 'b', []));

        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('jeux', ['locale' => 'fr'])));
        self::assertSame([['a', 'b'], 2], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertDirectoryDoesNotExist($this->directory);
    }

    /**
     * @return array{list<string>, int} the identifiers found, sorted, and the total
     */
    private function found(LoupeIndex $index, string $name, SearchQuery $query): array
    {
        $result = $index->search($name, $query);
        $ids = $result->ids;

        if (1 !== $query->limit) {
            sort($ids);
        }

        return [$ids, $result->total];
    }
}
