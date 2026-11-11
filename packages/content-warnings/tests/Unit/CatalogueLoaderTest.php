<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\CatalogueLoader;
use Phil\ContentWarnings\Exception\InvalidCatalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogueLoader::class)]
#[CoversClass(BaseCatalogue::class)]
#[CoversClass(InvalidCatalogue::class)]
final class CatalogueLoaderTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../Fixtures';

    public function testADefinitionBuildsACatalogue(): void
    {
        $catalogue = new CatalogueLoader()->apply($this->base());

        self::assertSame(['phobias', 'violence'], array_keys($catalogue->categories()));
        self::assertSame(['spiders', 'clowns', 'gore'], array_keys($catalogue->themes()));
        self::assertSame(['Clowns', 'Clowns'], [$catalogue->theme('clowns')?->label('fr'), $catalogue->theme('clowns')?->label('en')]);
        self::assertSame('Graphic blood', $catalogue->theme('gore')?->description('en'));
    }

    public function testADefinitionCompletesACatalogueAndKeepsItsOrder(): void
    {
        $loader = new CatalogueLoader();
        $catalogue = $loader->apply([
            'categories' => [
                'venues' => ['label' => ['en' => 'Venues']],
                'phobias' => ['label' => ['de' => 'Phobien']],
            ],
            'themes' => [
                'crowds' => ['category' => 'venues', 'label' => ['en' => 'Crowds']],
                'spiders' => ['label' => ['en' => 'Arachnids'], 'description' => ['en' => 'Any spider']],
                'gore' => ['category' => 'phobias'],
            ],
        ], $loader->apply($this->base()));

        self::assertSame(['phobias', 'violence', 'venues'], array_keys($catalogue->categories()));
        self::assertSame(['spiders', 'clowns', 'gore', 'crowds'], array_keys($catalogue->themes()));
        self::assertSame(['en' => 'Phobias', 'fr' => 'Phobies', 'de' => 'Phobien'], $catalogue->category('phobias')?->label->toArray());
        self::assertSame(['en' => 'Arachnids', 'fr' => 'Araignées'], $catalogue->theme('spiders')?->label->toArray());
        self::assertSame('Any spider', $catalogue->theme('spiders')->description('en'));
        self::assertSame(['phobias', 'Graphic blood'], [$catalogue->theme('gore')?->category, $catalogue->theme('gore')?->description('en')]);
        self::assertSame('phobias', $catalogue->theme('spiders')->category);
    }

    public function testADefinitionReplacesOrRemoves(): void
    {
        $loader = new CatalogueLoader();
        $base = $loader->apply($this->base());

        $replaced = $loader->apply(['replace' => true, 'categories' => ['venues' => ['label' => ['en' => 'Venues']]]], $base);
        self::assertSame([['venues'], []], [array_keys($replaced->categories()), array_keys($replaced->themes())]);

        $kept = $loader->apply(['replace' => false], $base);
        self::assertSame(['spiders', 'clowns', 'gore'], array_keys($kept->themes()));

        $removed = $loader->apply(['remove' => ['clowns', 'gore'], 'remove_categories' => ['violence']], $base);
        self::assertSame([['phobias'], ['spiders']], [array_keys($removed->categories()), array_keys($removed->themes())]);
    }

    public function testNumericKeysAreKeys(): void
    {
        $catalogue = new CatalogueLoader()->apply([
            'categories' => ['2024' => ['label' => ['en' => 'This year']]],
            'themes' => ['18' => ['category' => '2024', 'label' => ['en' => 'Adults only']]],
        ]);

        self::assertSame('This year', $catalogue->category('2024')?->label('en'));
        self::assertSame('Adults only', $catalogue->theme('18')?->label('en'));
    }

    public function testACategoryWithThemesLeftIsNotRemoved(): void
    {
        $this->expectExceptionMessageIsOrContains('the theme "gore" is in the unknown category "violence"');

        new CatalogueLoader()->apply(['remove_categories' => ['violence']], new CatalogueLoader()->apply($this->base()));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'an unknown key' => [['themes' => [], 'extra' => 1, 'other' => 2], 'a definition has the unknown key "extra", "other"'];
        yield 'a numbered key' => [[0 => 'x'], 'a definition has the unknown key "0"'];
        yield 'replace not a boolean' => [['replace' => 1], '"replace" is true or false'];
        yield 'categories not an array' => [['categories' => 'x'], '"categories" is not an array'];
        yield 'a category not an array' => [['categories' => ['venues' => 'x']], 'the category "venues" is not an array'];
        yield 'a category with an unknown key' => [['categories' => ['venues' => ['label' => ['en' => 'V'], 'icon' => 'x']]], 'the category "venues" has the unknown key "icon"'];
        yield 'a category without label' => [['categories' => ['venues' => []]], 'the category "venues" has no label'];
        yield 'a label not an array' => [['categories' => ['venues' => ['label' => 'Venues']]], '"label" is not an array'];
        yield 'a theme not an array' => [['themes' => ['crowds' => 'x']], 'the theme "crowds" is not an array'];
        yield 'a theme with an unknown key' => [['themes' => ['crowds' => ['severity' => 'x']]], 'the theme "crowds" has the unknown key "severity"'];
        yield 'a new theme without category' => [['themes' => ['crowds' => ['label' => ['en' => 'Crowds']]]], 'the theme "crowds" names no category'];
        yield 'a category that is no string' => [['themes' => ['crowds' => ['category' => 3, 'label' => ['en' => 'Crowds']]]], 'the theme "crowds" names no category'];
        yield 'a new theme in an unknown category' => [['themes' => ['crowds' => ['category' => 'venues', 'label' => ['en' => 'Crowds']]]], 'the theme "crowds" is in the unknown category "venues"'];
        yield 'a new theme without label' => [['themes' => ['crowds' => ['category' => 'phobias']]], 'the theme "crowds" has no label'];
        yield 'a description not an array' => [['themes' => ['gore' => ['description' => 'x']]], '"description" is not an array'];
        yield 'removing an unknown theme' => [['remove' => ['crowds']], 'the theme "crowds" to remove is not in the catalogue'];
        yield 'removing an unknown category' => [['remove_categories' => ['venues']], 'the category "venues" to remove is not in the catalogue'];
        yield 'remove not a list of keys' => [['remove' => [3]], '"remove" lists keys only'];
        yield 'remove not an array' => [['remove' => 'gore'], '"remove" is not an array'];
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    #[DataProvider('refused')]
    public function testAMalformedDefinitionIsRefused(array $definition, string $message): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $loader = new CatalogueLoader();
        $loader->apply($definition, $loader->apply($this->base()));
    }

    public function testADefinitionIsReadFromAPhpOrAJsonFile(): void
    {
        $loader = new CatalogueLoader();
        $catalogue = $loader->applyFile(self::FIXTURES.'/site.php', $loader->applyFile(self::FIXTURES.'/site.json', $loader->apply($this->base())));

        self::assertSame(['spiders', 'gore', 'crowds'], array_keys($catalogue->themes()));
        self::assertSame('Foules', $catalogue->theme('crowds')?->label('fr'));
        self::assertSame(['venues'], array_keys($loader->applyFile(self::FIXTURES.'/site.json')->categories()));
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.self::FIXTURES.'/missing.php" is not a readable file of 2000000 bytes or fewer');

        new CatalogueLoader()->applyFile(self::FIXTURES.'/missing.php');
    }

    public function testADirectoryIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('is not a readable file');

        new CatalogueLoader()->applyFile(self::FIXTURES);
    }

    public function testAFileOverTwoMegabytesIsRefused(): void
    {
        $path = sys_get_temp_dir().'/phil-content-warnings-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($path, str_repeat(' ', 2_000_000).'{}');

        try {
            $this->expectExceptionMessageIsOrContains('is not a readable file of 2000000 bytes or fewer');
            new CatalogueLoader()->applyFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testAFileOfTwoMegabytesIsRead(): void
    {
        $path = sys_get_temp_dir().'/phil-content-warnings-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($path, str_repeat(' ', 1_999_998).'{}');

        try {
            self::assertSame([], new CatalogueLoader()->applyFile($path)->themes());
        } finally {
            unlink($path);
        }
    }

    public function testBrokenJsonIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('/broken.json" is not JSON: Syntax error');

        new CatalogueLoader()->applyFile(self::FIXTURES.'/broken.json');
    }

    public function testJsonNestedTooDeeplyIsRefused(): void
    {
        $path = sys_get_temp_dir().'/phil-content-warnings-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($path, str_repeat('[', 16).str_repeat(']', 16));

        try {
            $this->expectExceptionMessageIsOrContains('is not JSON: Maximum stack depth exceeded');
            new CatalogueLoader()->applyFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testJsonNestedFifteenDeepIsRead(): void
    {
        $path = sys_get_temp_dir().'/phil-content-warnings-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($path, '{"themes": {"a": {"category": "x", "label": '.str_repeat('[', 12).str_repeat(']', 12).'}}}');

        try {
            $this->expectExceptionMessageIsOrContains('"0" is not a locale');
            new CatalogueLoader()->applyFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testAFileWithoutADefinitionIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('/scalar.json" holds no definition');

        new CatalogueLoader()->applyFile(self::FIXTURES.'/scalar.json');
    }

    public function testTheBaseCatalogueIsLabelledInSixLanguages(): void
    {
        $catalogue = BaseCatalogue::load();

        self::assertCount(12, $catalogue->categories());
        self::assertGreaterThanOrEqual(70, \count($catalogue->themes()));

        foreach ([...$catalogue->categories(), ...$catalogue->themes()] as $entry) {
            self::assertSame(['en', 'fr', 'es', 'de', 'it', 'pt'], array_keys($entry->label->toArray()), $entry->key);
        }

        self::assertSame(['Spiders', 'Araignées', 'phobias'], [$catalogue->theme('spiders')?->label('en'), $catalogue->theme('spiders')?->label('fr_CA'), $catalogue->theme('spiders')?->category]);
    }

    /**
     * @return array<string, mixed>
     */
    private function base(): array
    {
        return [
            'categories' => [
                'phobias' => ['label' => ['en' => 'Phobias', 'fr' => 'Phobies']],
                'violence' => ['label' => ['en' => 'Violence']],
            ],
            'themes' => [
                'spiders' => ['category' => 'phobias', 'label' => ['en' => 'Spiders', 'fr' => 'Araignées']],
                'clowns' => ['category' => 'phobias', 'label' => ['en' => 'Clowns']],
                'gore' => ['category' => 'violence', 'label' => ['en' => 'Gore'], 'description' => ['en' => 'Graphic blood']],
            ],
        ];
    }
}
