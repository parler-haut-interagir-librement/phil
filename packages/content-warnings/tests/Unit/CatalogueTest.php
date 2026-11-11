<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Category;
use Phil\ContentWarnings\Exception\InvalidCatalogue;
use Phil\ContentWarnings\LocalizedText;
use Phil\ContentWarnings\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Catalogue::class)]
#[CoversClass(Category::class)]
#[CoversClass(Theme::class)]
final class CatalogueTest extends TestCase
{
    public function testACategoryAndAThemeAreLabelledInTheLocale(): void
    {
        $category = new Category('phobias', LocalizedText::of(['fr' => 'Phobies', 'en' => 'Phobias']));
        $theme = new Theme('spiders', 'phobias', LocalizedText::of(['fr' => 'Araignées', 'en' => 'Spiders']), LocalizedText::of(['fr' => 'Des araignées', 'en' => 'Spiders, seen or described']));

        self::assertSame(['phobias', 'Phobies', 'Phobias', 'Phobies'], [$category->key, $category->label('fr_CA'), $category->label('ja'), $category->label('ja', [])]);
        self::assertSame(['spiders', 'phobias', 'Araignées', 'Spiders', 'Araignées'], [$theme->key, $theme->category, $theme->label('fr'), $theme->label('ja'), $theme->label('ja', [])]);
        self::assertSame(['Des araignées', 'Spiders, seen or described', 'Des araignées', ''], [$theme->description('fr'), $theme->description('ja'), $theme->description('ja', []), new Theme('snakes', 'phobias', LocalizedText::of(['en' => 'Snakes']))->description('en')]);
        self::assertTrue(new Theme('snakes', 'phobias', LocalizedText::of(['en' => 'Snakes']))->description->isEmpty());
    }

    public function testACatalogueFindsItsThemesAndCategoriesInOrder(): void
    {
        $catalogue = $this->catalogue();

        self::assertSame(['phobias', 'violence'], array_keys($catalogue->categories()));
        self::assertSame(['spiders', 'gore', 'snakes'], array_keys($catalogue->themes()));
        self::assertSame(['spiders', 'snakes'], array_keys($catalogue->themesIn('phobias')));
        self::assertSame([], $catalogue->themesIn('unknown'));
        self::assertSame('gore', $catalogue->theme('gore')?->key);
        self::assertNull($catalogue->theme('clowns'));
        self::assertTrue($catalogue->has('gore'));
        self::assertFalse($catalogue->has('clowns'));
        self::assertSame('violence', $catalogue->category('violence')?->key);
        self::assertNull($catalogue->category('gore'));
        self::assertSame([], new Catalogue()->themes());
    }

    public function testAThemeIsInACategoryOfTheCatalogue(): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains('the theme "gore" is in the unknown category "violence"');

        new Catalogue([$this->category('phobias')], [$this->theme('gore', 'violence')]);
    }

    public function testACategoryIsGivenOnce(): void
    {
        $this->expectExceptionMessageIsOrContains('the category "phobias" is given twice');

        new Catalogue([$this->category('phobias'), $this->category('phobias')]);
    }

    public function testAThemeIsGivenOnce(): void
    {
        $this->expectExceptionMessageIsOrContains('the theme "gore" is given twice');

        new Catalogue([$this->category('violence')], [$this->theme('gore', 'violence'), $this->theme('gore', 'violence')]);
    }

    public function testACatalogueHasAtMostTwoHundredCategories(): void
    {
        $categories = array_map(fn (int $index): Category => $this->category('c'.$index), range(1, 200));
        self::assertCount(200, new Catalogue($categories)->categories());

        $this->expectExceptionMessageIsOrContains('a catalogue has at most 200 categories and 2000 themes');
        new Catalogue([...$categories, $this->category('c201')]);
    }

    public function testACatalogueHasAtMostTwoThousandThemes(): void
    {
        $themes = array_map(fn (int $index): Theme => $this->theme('t'.$index, 'c'), range(1, 2000));
        self::assertCount(2000, new Catalogue([$this->category('c')], $themes)->themes());

        $this->expectExceptionMessageIsOrContains('a catalogue has at most 200 categories and 2000 themes');
        new Catalogue([$this->category('c')], [...$themes, $this->theme('t2001', 'c')]);
    }

    public function testMalformedKeysAndMissingLabelsAreRefused(): void
    {
        $refusals = [];

        foreach ([
            static fn (): Category => new Category('Bad Key', LocalizedText::of(['en' => 'x'])),
            static fn (): Category => new Category('empty', LocalizedText::empty()),
            static fn (): Theme => new Theme('x'.str_repeat('é', 89), 'c', LocalizedText::of(['en' => 'x'])),
            static fn (): Theme => new Theme('ok', 'Bad', LocalizedText::of(['en' => 'x'])),
            static fn (): Theme => new Theme('ok', 'c', LocalizedText::empty()),
        ] as $build) {
            try {
                $build();
            } catch (InvalidCatalogue $exception) {
                $refusals[] = $exception->getMessage();
            }
        }

        self::assertSame([
            'Invalid content warning catalogue: "Bad Key" is not a category key.',
            'Invalid content warning catalogue: the category "empty" has no label.',
            'Invalid content warning catalogue: "x'.str_repeat('é', 79).'" is not a theme key.',
            'Invalid content warning catalogue: "Bad" is not a category key.',
            'Invalid content warning catalogue: the theme "ok" has no label.',
        ], $refusals);
    }

    public function testALongCategoryKeyIsCutInItsMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"x'.str_repeat('é', 79).'" is not a category key');

        new Category('x'.str_repeat('é', 89), LocalizedText::of(['en' => 'x']));
    }

    public function testALongThemeCategoryKeyIsCutInItsMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"x'.str_repeat('é', 79).'" is not a category key');

        new Theme('ok', 'x'.str_repeat('é', 89), LocalizedText::of(['en' => 'x']));
    }

    private function catalogue(): Catalogue
    {
        return new Catalogue(
            [$this->category('phobias'), $this->category('violence')],
            [$this->theme('spiders', 'phobias'), $this->theme('gore', 'violence'), $this->theme('snakes', 'phobias')],
        );
    }

    private function category(string $key): Category
    {
        return new Category($key, LocalizedText::of(['en' => ucfirst($key)]));
    }

    private function theme(string $key, string $category): Theme
    {
        return new Theme($key, $category, LocalizedText::of(['en' => ucfirst($key)]));
    }
}
