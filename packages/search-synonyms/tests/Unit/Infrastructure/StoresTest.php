<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Tests\Unit\Infrastructure;

use Override;
use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;
use Phil\SearchSynonyms\Infrastructure\Store\FileSynonymStore;
use Phil\SearchSynonyms\Infrastructure\Store\InMemorySynonymStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(InMemorySynonymStore::class)]
#[CoversClass(FileSynonymStore::class)]
final class StoresTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-search-synonyms-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->files() as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testMemoryKeepsAListPerLanguage(): void
    {
        $french = SynonymList::of('fr', [SynonymSet::equivalent(['jdr', 'jeu de rôle'])]);
        $store = new InMemorySynonymStore($french, SynonymList::of('en'));

        self::assertSame($french, $store->get('fr-CA'));
        self::assertEquals(SynonymList::of('de'), $store->get('de'));
        self::assertSame(['en', 'fr'], $store->languages());

        $english = SynonymList::of('en', [SynonymSet::oneWay(['rpg'], ['rpg', 'role-playing game'])]);
        $store->save($english);
        self::assertSame($english, $store->get('en'));
        self::assertSame([], new InMemorySynonymStore()->languages());
    }

    public function testFilesKeepAListPerLanguage(): void
    {
        $store = new FileSynonymStore($this->directory.'/');
        self::assertEquals(SynonymList::of('fr'), $store->get('fr'), 'no directory yet');
        self::assertSame([], $store->languages());

        $french = SynonymList::of('fr', [SynonymSet::equivalent(['jdr', 'jeu de rôle']), SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon'])]);
        $store->save($french);
        $store->save(SynonymList::of('en'));
        file_put_contents($this->directory.'/notes.txt', 'x');
        file_put_contents($this->directory.'/de.json', '{}');

        self::assertEquals($french, $store->get('fr_CA'));
        self::assertSame("# Synonyms, language fr\njdr, jeu de rôle\ndonjon => donjon, dungeon\n", file_get_contents($this->directory.'/fr.txt'));
        self::assertSame(['en', 'fr'], $store->languages());
        self::assertSame(['de.json', 'en.txt', 'fr.txt', 'notes.txt'], array_map(basename(...), $this->files()), 'no file left behind');

        file_put_contents($this->directory.'/fr.txt', "jdr, jeu de rôle\nseul\n");
        $this->expectException(InvalidSynonyms::class);
        $this->expectExceptionMessageIsOrContains('Line 2: equivalent synonyms are two phrases or more.');
        $store->get('fr');
    }

    public function testAFileTooLargeIsRefusedUnread(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory.'/fr.txt', str_repeat('#', 1_048_577));

        $this->expectException(InvalidSynonyms::class);
        $this->expectExceptionMessageIsOrContains('A synonyms file is 1048576 bytes at most.');
        new FileSynonymStore($this->directory)->get('fr');
    }

    public function testAStoreThatCannotWriteSaysSo(): void
    {
        file_put_contents($this->directory, 'a file where the directory should be');

        try {
            new FileSynonymStore($this->directory)->save(SynonymList::of('fr'));
            self::fail('The store wrote.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The directory of the synonyms, %s, cannot be created.', $this->directory), $runtimeException->getMessage());
        } finally {
            unlink($this->directory);
        }

        mkdir($this->directory);
        mkdir($this->directory.'/fr.txt');

        try {
            new FileSynonymStore($this->directory)->save(SynonymList::of('fr'));
            self::fail('The store wrote.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The synonyms of "fr" cannot be written to %s/fr.txt.', $this->directory), $runtimeException->getMessage());
            self::assertSame([$this->directory.'/fr.txt'], $this->files(), 'nothing written beside it');
        } finally {
            rmdir($this->directory.'/fr.txt');
        }
    }

    public function testALanguageIsAFileOfTwoOrThreeLettersWithTheExtension(): void
    {
        mkdir($this->directory);

        foreach (['fr.txt', 'zz', 'eng.txt', "fr\n.txt", 'abcd.txt', 'a.txt', 'FR.txt'] as $name) {
            file_put_contents($this->directory.'/'.$name, 'x');
        }

        self::assertSame(['eng', 'fr'], new FileSynonymStore($this->directory)->languages());
    }

    public function testTheLanguagesAreSorted(): void
    {
        $store = new FileSynonymStore($this->directory);

        foreach (['fr', 'de', 'en', 'fra'] as $language) {
            $store->save(SynonymList::of($language));
        }

        self::assertSame(['de', 'en', 'fr', 'fra'], $store->languages());
    }

    public function testADirectoryWithASlashAtTheEndGivesTheSamePaths(): void
    {
        mkdir($this->directory);
        mkdir($this->directory.'/fr.txt');

        try {
            new FileSynonymStore($this->directory.'/')->save(SynonymList::of('fr'));
            self::fail('The store wrote.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The synonyms of "fr" cannot be written to %s/fr.txt.', $this->directory), $runtimeException->getMessage());
        } finally {
            rmdir($this->directory.'/fr.txt');
        }
    }

    public function testTheDirectoryIsCreatedForAGroupOfWriters(): void
    {
        $umask = umask(0);

        try {
            new FileSynonymStore($this->directory.'/a/b')->save(SynonymList::of('fr'));
            self::assertSame(0o775, fileperms($this->directory.'/a/b') & 0o777);
            self::assertSame(0o775, fileperms($this->directory.'/a') & 0o777);
        } finally {
            umask($umask);
            unlink($this->directory.'/a/b/fr.txt');
            rmdir($this->directory.'/a/b');
            rmdir($this->directory.'/a');
        }
    }

    public function testADirectoryThatCannotBeCreatedIsSaidSo(): void
    {
        file_put_contents($this->directory, 'a file');
        set_error_handler(static fn (): bool => true);

        try {
            new FileSynonymStore($this->directory.'/inside')->save(SynonymList::of('fr'));
            self::fail('The store wrote.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(\sprintf('The directory of the synonyms, %s/inside, cannot be created.', $this->directory), $runtimeException->getMessage());
        } finally {
            restore_error_handler();
            unlink($this->directory);
        }
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = glob($this->directory.'/*');

        return false === $files ? [] : $files;
    }
}
