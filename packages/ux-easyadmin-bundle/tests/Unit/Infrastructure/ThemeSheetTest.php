<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Unit\Infrastructure;

use Composer\InstalledVersions;
use Phil\UxEasyAdminBundle\Infrastructure\Stylesheet\ThemeSheet;
use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ThemeSheet::class)]
final class ThemeSheetTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/Fixtures';

    private const array STATES = ['primary', 'secondary', 'success', 'info', 'warning', 'danger'];

    /**
     * Only a whole line importing a token file by a plain lower-case name into a named layer becomes that layer; the rest
     * is kept as written.
     */
    public function testEachImportOfATokenFileBecomesItsLayer(): void
    {
        self::assertSame(
            <<<'CSS'
                /* Header */
                @layer one, two;

                @layer one.inner-part {
                    :root {
                        --a: 1;

                        --b: 2;
                    }
                }
                  @import url("tokens/first-tokens.css") layer(one);
                @import url("tokens/../tokens/first-tokens.css") layer(one);
                @import url("tokens/first-tokens.css") layer(one) screen;
                @import url("other/first-tokens.css") layer(one);
                @import url("tokens/first-tokens.css") layer(one); /* after */
                @import url("tokens/.css") layer(one);
                @import url("tokens/first-tokens.css") layer();
                @import url("tokens/First.css") layer(one);
                @import url("tokens/first-tokens.css") layer(One);

                @layer two {
                    a { color: red; }
                }

                CSS,
            ThemeSheet::build(self::FIXTURES.'/source.css', self::FIXTURES.'/tokens'),
        );
    }

    /**
     * Every variable the theme gives a value is one EasyAdmin declares in its own stylesheets, in the light scheme and
     * in the dark one: a variable EasyAdmin renamed or dropped would leave its part of the back-office unthemed.
     */
    public function testEveryVariableOfTheThemeIsOneOfEasyAdmin(): void
    {
        $easyAdmin = $this->stylesheets($this->easyAdminStyles());
        $variables = $this->declared($this->themeSource());

        self::assertCount(59, $variables);

        foreach ($variables as $variable) {
            self::assertMatchesRegularExpression('/'.preg_quote($variable, '/').'\s*:/', $easyAdmin, $variable.' is not a variable of EasyAdmin.');
        }

        self::assertStringContainsString('.ea-dark-scheme', $easyAdmin, 'EasyAdmin names its dark scheme by a class of the body.');
    }

    /**
     * Every part of the back-office the theme dresses keeps its variables: the accent, the page and the sidebar, the
     * tables, the forms, the alerts and badges of every state, the corners, the focus and the fonts.
     *
     * @param list<string> $variables
     */
    #[DataProvider('parts')]
    public function testEachPartOfTheBackOfficeIsThemed(array $variables): void
    {
        $declared = $this->declared($this->themeSource());

        foreach ($variables as $variable) {
            self::assertContains($variable, $declared);
        }
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function parts(): iterable
    {
        yield 'the accent' => [['--ea-primary', '--ea-primary-foreground', '--ea-primary-on-surface']];
        yield 'the sidebar' => [['--sidebar-bg', '--sidebar-menu-color', '--sidebar-menu-active-item-bg', '--sidebar-menu-active-item-color']];
        yield 'the tables' => [['--table-cell-border-color', '--table-hover-cell-bg']];
        yield 'the forms' => [['--form-input-border-color', '--form-input-text-color']];
        yield 'the alerts' => [array_merge(...array_map(static fn (string $state): array => ['--alert-'.$state.'-bg', '--alert-'.$state.'-color', '--alert-'.$state.'-border-color'], self::STATES))];
        yield 'the badges' => [array_merge(...array_map(static fn (string $state): array => ['--badge-'.$state.'-bg', '--badge-'.$state.'-color'], self::STATES))];
        yield 'the corners, the focus and the fonts' => [['--ea-radius', '--border-radius', '--ea-focus-ring-width', '--font-family-sans-serif', '--font-family-monospace']];
    }

    /**
     * Every token the theme reads is one phil/ux-css declares, and the theme's layer comes after every layer of
     * EasyAdmin, its tokens under EasyAdmin's `vendor` layer.
     */
    public function testTheThemeReadsTokensOfPhilUxCssInEasyAdminsLayers(): void
    {
        $source = $this->themeSource();
        $tokens = $this->stylesheets(ThemeSheet::tokensDirectory());

        self::assertSame(59, preg_match_all('/var\((--phil-[a-z0-9-]+)\)/', $source, $read));

        foreach (array_unique($read[1]) as $token) {
            self::assertMatchesRegularExpression('/'.preg_quote($token, '/').'\s*:/', $tokens, $token.' is not a token of phil/ux-css.');
        }

        self::assertStringContainsString('@layer ea-overrides, ea-defaults, vendor, ea;', (string) file_get_contents($this->easyAdminStyles().'/layers.css'));
        self::assertStringContainsString('@layer ea-overrides, ea-defaults, vendor, ea, phil-easyadmin;', $source);
        self::assertSame(3, preg_match_all('/^@import url\("tokens\/[a-z]+\.css"\) layer\(vendor\.phil\.[a-z]+\);$/m', $source));
    }

    public function testAMissingTokenFileIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The stylesheet "'.self::FIXTURES.'/missing/first-tokens.css" cannot be read.');

        ThemeSheet::build(self::FIXTURES.'/source.css', self::FIXTURES.'/missing');
    }

    public function testADirectoryIsNotAStylesheet(): void
    {
        $this->expectException(RuntimeException::class);

        ThemeSheet::build(self::FIXTURES.'/tokens', self::FIXTURES.'/tokens');
    }

    /**
     * The stylesheet the bundle serves is the one its source and the installed phil/ux-css give: `php bin/build.php`
     * writes it again.
     */
    public function testTheServedStylesheetIsUpToDate(): void
    {
        $tokens = ThemeSheet::tokensDirectory();

        self::assertFileExists($tokens.'/colors.css');
        self::assertStringEndsWith('/ux-css/css/tokens', $tokens);
        self::assertStringEqualsFile(
            PhilUxEasyAdminBundle::STYLES_DIR.'/phil-ux-easyadmin.css',
            ThemeSheet::build(PhilUxEasyAdminBundle::STYLES_DIR.'/../src/phil-ux-easyadmin.css', $tokens),
        );
    }

    private function themeSource(): string
    {
        return (string) file_get_contents(PhilUxEasyAdminBundle::STYLES_DIR.'/../src/phil-ux-easyadmin.css');
    }

    /**
     * The stylesheets of EasyAdmin's sources, where Composer installed it.
     */
    private function easyAdminStyles(): string
    {
        $path = InstalledVersions::getInstallPath('easycorp/easyadmin-bundle');
        self::assertIsString($path);

        return $path.'/assets/css';
    }

    /**
     * Every stylesheet of a directory and its subdirectories, one after the other.
     */
    private function stylesheets(string $directory): string
    {
        $files = glob($directory.'/{,*/}*.css', \GLOB_BRACE);
        self::assertNotFalse($files);
        self::assertNotSame([], $files);

        return implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
    }

    /**
     * The custom properties a stylesheet gives a value, other than the tokens of phil/ux-css.
     *
     * @return list<string>
     */
    private function declared(string $css): array
    {
        preg_match_all('/^\s+(--(?!phil-)[a-z0-9-]+)\s*:/m', $css, $declared);

        return array_values(array_unique($declared[1]));
    }
}
