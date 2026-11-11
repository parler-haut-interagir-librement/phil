<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Phil\ContentWarningsBundle\Application\WarningScreen;
use Phil\ContentWarningsBundle\Infrastructure\Memory\InMemoryWarningPreferences;
use Phil\ContentWarningsBundle\Infrastructure\Twig\ContentWarningsExtension;
use Phil\ContentWarningsBundle\Tests\Unit\Fixtures\Catalogues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentWarningsExtension::class)]
final class ContentWarningsExtensionTest extends TestCase
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

    public function testTheFunctionsDecideAndLabel(): void
    {
        $store = new InMemoryWarningPreferences();
        $store->save(WarningPreferences::defaults()->withTheme('gore', Display::Hide));

        $extension = new ContentWarningsExtension(new WarningScreen(Catalogues::small(), $store), new CatalogueLabels(Catalogues::small()));

        self::assertSame(Display::Hide, $extension->decision(ContentWarnings::forThemes('gore', 'war'))->display);
        self::assertSame(Display::Show, $extension->decision(null)->display);
        self::assertSame(['Araignées', 'Spiders'], [$extension->label('spiders'), $extension->label('spiders', 'en')]);
        self::assertSame(['Spiders, seen or described', ''], [$extension->description('spiders', 'en'), $extension->description('gore')]);
        self::assertSame(['Phobies', 'Phobias', ''], [$extension->category('spiders'), $extension->category('spiders', 'en'), $extension->category('unknown')]);
    }
}
