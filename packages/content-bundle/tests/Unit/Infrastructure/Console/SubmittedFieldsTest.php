<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Console;

use Override;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Infrastructure\Console\SubmittedFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmittedFields::class)]
final class SubmittedFieldsTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-content-fields-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (array_diff((array) scandir($this->directory), ['.', '..']) as $file) {
            unlink($this->directory.'/'.$file);
        }

        rmdir($this->directory);
    }

    public function testFieldsAreStringsAndDotsReachIntoAMap(): void
    {
        self::assertSame(
            ['slug' => 'ouverture', 'templateData' => ['title' => 'Bonjour = salut', 'block' => ['level' => '2']]],
            SubmittedFields::read(null, ['slug=ouverture', 'templateData.title=Bonjour = salut', 'templateData.block.level=2'], []),
        );
    }

    public function testJsonValuesAreTyped(): void
    {
        self::assertSame(
            ['seoNoIndex' => true, 'slug' => null, 'seoKeywords' => ['larp', '2026']],
            SubmittedFields::read(null, [], ['seoNoIndex=true', 'slug=null', 'seoKeywords=["larp","2026"]']),
        );
    }

    public function testAYamlFileIsReadAndOptionsOverrideIt(): void
    {
        file_put_contents($this->directory.'/news.yaml', "slug: ouverture\nbodySource: |\n  # Titre\n\n  Texte\ntemplateData:\n  title: Bonjour\n");

        self::assertSame(
            ['slug' => 'grande-ouverture', 'bodySource' => "# Titre\n\nTexte\n", 'templateData' => ['title' => 'Bonjour', 'subtitle' => 'Salut']],
            SubmittedFields::read($this->directory.'/news.yaml', ['slug=grande-ouverture', 'templateData.subtitle=Salut'], []),
        );
    }

    public function testAJsonFileIsRead(): void
    {
        file_put_contents($this->directory.'/news.json', '{"slug": "ouverture", "seoNoIndex": false}');

        self::assertSame(['slug' => 'ouverture', 'seoNoIndex' => false], SubmittedFields::read($this->directory.'/news.json', [], []));
    }

    public function testTheExtensionIsReadWhateverItsCase(): void
    {
        file_put_contents($this->directory.'/news.YAML', 'slug: ouverture');

        self::assertSame(['slug' => 'ouverture'], SubmittedFields::read($this->directory.'/news.YAML', [], []));
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('does not exist');

        SubmittedFields::read($this->directory, [], []);
    }

    /**
     * @param list<string> $fields
     * @param list<string> $json
     */
    #[DataProvider('refusals')]
    public function testMalformedInputIsRefused(?string $file, string $content, array $fields, array $json, string $message): void
    {
        if (null !== $file) {
            file_put_contents($this->directory.'/'.$file, $content);
            $file = $this->directory.'/'.$file;
        }

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageMatches($message);

        SubmittedFields::read($file, $fields, $json);
    }

    /**
     * @return iterable<string, array{?string, string, list<string>, list<string>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'field without equals' => [null, '', ['slug'], [], '/--field takes "key=value"; got "slug"/'];
        yield 'field without key' => [null, '', ['=x'], [], '/--field takes "key=value"/'];
        yield 'json that is not' => [null, '', [], ['seoNoIndex=yes'], '/The value of "seoNoIndex" is not JSON/'];
        yield 'dotted into a string' => [null, '', ['slug=x', 'slug.part=y'], [], '/"slug" must hold a map of keys to values/'];
        yield 'unknown extension' => ['news.txt', 'slug: x', [], [], '/is neither YAML \(\.yaml, \.yml\) nor JSON \(\.json\)/'];
        yield 'yaml that is not a map' => ['news.yml', '- a', [], [], '/must hold a map of keys to values; got the key 0/'];
        yield 'yaml scalar' => ['news.yaml', 'just text', [], [], '/must hold a map of keys to values\./'];
        yield 'broken json' => ['news.json', '{', [], [], '/cannot be parsed/'];
        yield 'broken yaml' => ['news.yaml', "a: [\n", [], [], '/cannot be parsed/'];
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('does not exist');

        SubmittedFields::read($this->directory.'/absent.yaml', [], []);
    }
}
