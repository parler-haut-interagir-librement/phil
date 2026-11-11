<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit\Application;

use Locale;
use Override;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Exception\InvalidCatalogue;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\CatalogueFactory;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Phil\ContentWarningsBundle\Application\Exception\PreferencesNotSaved;
use Phil\ContentWarningsBundle\Application\WarningScreen;
use Phil\ContentWarningsBundle\Infrastructure\Memory\InMemoryWarningPreferences;
use Phil\ContentWarningsBundle\Tests\Unit\Fixtures\Catalogues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogueFactory::class)]
#[CoversClass(CatalogueLabels::class)]
#[CoversClass(WarningScreen::class)]
#[CoversClass(PreferencesNotSaved::class)]
final class ApplicationTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testTheCatalogueIsTheBaseListThenTheFilesThenTheDefinition(): void
    {
        $file = __DIR__.'/../Fixtures/site.json';

        self::assertTrue(new CatalogueFactory()->create()->has('spiders'));
        self::assertSame([], new CatalogueFactory(false)->create()->themes());

        $catalogue = new CatalogueFactory(true, [$file], ['remove' => ['crowds', 'spiders']])->create();
        self::assertTrue($catalogue->has('gore'));
        self::assertFalse($catalogue->has('spiders'));
        self::assertNotNull($catalogue->category('venues'));

        $catalogue = new CatalogueFactory(false, [$file])->create();
        self::assertSame(['crowds'], array_keys($catalogue->themes()));
    }

    public function testABrokenDefinitionIsRefused(): void
    {
        $this->expectException(InvalidCatalogue::class);

        new CatalogueFactory(false, [], ['remove' => ['gore']])->create();
    }

    public function testLabelsAreInTheCurrentLocaleThenTheFallbacks(): void
    {
        $labels = new CatalogueLabels(Catalogues::small());

        self::assertSame(['Araignées', 'Spiders', 'unknown'], [$labels->theme('spiders'), $labels->theme('spiders', 'de'), $labels->theme('unknown')]);
        self::assertSame(['Phobies', 'Phobias', 'unknown'], [$labels->category('phobias'), $labels->category('phobias', 'ja'), $labels->category('unknown')]);
        self::assertSame(['Des araignées', 'Spiders, seen or described', 'Spiders, seen or described', '', ''], [$labels->description('spiders'), $labels->description('spiders', 'en'), $labels->description('spiders', 'de'), $labels->description('gore'), $labels->description('unknown')]);
        self::assertSame('War', $labels->theme('war', 'de'));
        self::assertSame(['Phobies' => ['Araignées' => 'spiders'], 'Violence' => ['Gore' => 'gore', 'Guerre' => 'war']], $labels->choices());
        self::assertSame(['Phobias' => ['Spiders' => 'spiders'], 'Violence' => ['Gore' => 'gore', 'War' => 'war']], $labels->choices('en'));
        self::assertEquals(Catalogues::small()->themes(), $labels->catalogue()->themes());

        $french = new CatalogueLabels(Catalogues::small(), ['fr']);
        self::assertSame(['Araignées', 'Phobies', 'Des araignées'], [$french->theme('spiders', 'de'), $french->category('phobias', 'de'), $french->description('spiders', 'de')]);
    }

    public function testTheScreenDecidesForTheCurrentMember(): void
    {
        $store = new InMemoryWarningPreferences(Display::Show);
        $screen = new WarningScreen(Catalogues::small(), $store);

        self::assertSame(Display::Show, $screen->decide(null)->display);
        self::assertSame(Display::Show, $screen->decide(ContentWarnings::forThemes('war'))->display);

        $store->save(WarningPreferences::defaults(Display::Show)->withCategory('violence', Display::Hide));
        self::assertSame(Display::Hide, $screen->decide(ContentWarnings::forThemes('war'))->display);
        self::assertSame([], $screen->decide(null)->warnings);
    }

    public function testAPreferenceNotSavedSaysWhy(): void
    {
        self::assertSame('The warning preferences cannot be saved: there is no session.', PreferencesNotSaved::because('there is no session.')->getMessage());
    }
}
