<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The kit as the Toolkit reads it: the manifests, the files they copy, the recipes they depend on, the controllers the
 * templates name, and the archive ux:install downloads, which holds the recipes alone.
 */
#[CoversNothing]
final class KitTest extends TestCase
{
    private const string KIT = __DIR__.'/../..';

    /**
     * The directories of the package that are not recipes, left out of the archive by `.gitattributes`.
     */
    private const array PACKAGE_DIRECTORIES = ['doc', 'src', 'tests'];

    private const array RECIPES = [
        'activity-calendar' => ['component', 'ActivityCalendar'],
        'animated-button' => ['component', 'AnimatedButton'],
        'animated-headline' => ['component', 'AnimatedHeadline'],
        'autosave-status' => ['component', 'AutosaveStatus'],
        'before-after' => ['component', 'BeforeAfter'],
        'carousel' => ['component', 'Carousel'],
        'celebration' => ['component', 'Celebration'],
        'chart' => ['component', 'Chart'],
        'chart-js' => ['component', 'ChartJs'],
        'color-picker' => ['component', 'ColorPicker'],
        'coming-soon' => ['block', 'ComingSoon'],
        'copy-button' => ['component', 'CopyButton'],
        'figure' => ['component', 'Figure'],
        'file-input' => ['component', 'FileInput'],
        'floating-label' => ['component', 'FloatingLabel'],
        'gallery' => ['component', 'Gallery'],
        'hero' => ['component', 'Hero'],
        'hotspots' => ['component', 'Hotspots'],
        'illustration' => ['component', 'Illustration'],
        'image' => ['component', 'Image'],
        'lightbox' => ['component', 'Lightbox'],
        'login-form' => ['block', 'LoginForm'],
        'logo' => ['component', 'Logo'],
        'number-input' => ['component', 'NumberInput'],
        'podium' => ['component', 'Podium'],
        'progress-ring' => ['component', 'ProgressRing'],
        'qr-code' => ['component', 'QrCode'],
        'rating' => ['component', 'Rating'],
        'reaction-button' => ['component', 'ReactionButton'],
        'reaction-picker' => ['component', 'ReactionPicker'],
        'relative-time' => ['component', 'RelativeTime'],
        'scratch-card' => ['component', 'ScratchCard'],
        'seasonal-decor' => ['component', 'SeasonalDecor'],
        'signup-form' => ['block', 'SignupForm'],
        'sparkline' => ['component', 'Sparkline'],
        'stepper' => ['component', 'Stepper'],
        'sticky-header' => ['component', 'StickyHeader'],
        'tag' => ['component', 'Tag'],
        'theme-switcher' => ['component', 'ThemeSwitcher'],
        'timeline' => ['component', 'Timeline'],
        'tree' => ['component', 'Tree'],
        'video' => ['component', 'Video'],
    ];

    public function testTheKitManifestHasTheFieldsOfTheToolkit(): void
    {
        $manifest = $this->json(self::KIT.'/manifest.json');

        foreach (['name', 'description', 'license', 'homepage'] as $field) {
            self::assertIsString($manifest[$field] ?? null, $field);
        }

        self::assertSame('PHIL UX for Shadcn', $manifest['name']);
        self::assertSame('MIT', $manifest['license']);
        self::assertIsString($manifest['icon'] ?? null);
        self::assertFileExists(self::KIT.'/'.$manifest['icon']);
    }

    public function testEveryDirectoryIsARecipeOrLeftOutOfTheArchive(): void
    {
        $directories = glob(self::KIT.'/*', \GLOB_ONLYDIR);
        self::assertIsArray($directories);
        $names = array_map(basename(...), $directories);

        // An installation of the package on its own adds `vendor/`, which the repository ignores.
        self::assertSame(array_keys(self::RECIPES), array_values(array_diff($names, [...self::PACKAGE_DIRECTORIES, 'vendor', 'var'])));

        $attributes = (string) file_get_contents(self::KIT.'/.gitattributes');

        foreach (self::PACKAGE_DIRECTORIES as $directory) {
            self::assertStringContainsString('/'.$directory.' export-ignore', $attributes);
        }
    }

    public function testEachRecipeManifestCopiesFilesThatExistIntoTheToolkitDirectories(): void
    {
        foreach (self::RECIPES as $directory => [$type, $name]) {
            $manifest = $this->json(self::KIT.'/'.$directory.'/manifest.json');

            self::assertSame($type, $manifest['type'] ?? null, $directory);
            self::assertSame($name, $manifest['name'] ?? null, $directory);
            self::assertFileExists(self::KIT.'/'.$directory.'/README.md');
            self::assertIsArray($manifest['copy-files'] ?? null);
            self::assertNotSame([], $manifest['copy-files']);

            foreach ($manifest['copy-files'] as $source => $destination) {
                self::assertIsString($source);
                self::assertIsString($destination);
                self::assertFileExists(self::KIT.'/'.$directory.'/'.$source);
                self::assertMatchesRegularExpression('#^%(COMPONENTS|CONTROLLERS)_DIR%/#', $destination);
            }

            self::assertFileExists(\sprintf('%s/%s/templates/components/%s.html.twig', self::KIT, $directory, $name));

            $dependencies = $manifest['dependencies'] ?? [];
            self::assertIsArray($dependencies);

            foreach ((array) ($dependencies['recipe'] ?? []) as $dependency) {
                self::assertIsString($dependency);
                self::assertArrayHasKey($dependency, self::RECIPES, $directory.' depends on a recipe of the kit');
            }
        }
    }

    public function testEachControllerATemplateNamesIsCopiedByItsRecipe(): void
    {
        foreach (array_keys(self::RECIPES) as $directory) {
            $templates = glob(self::KIT.'/'.$directory.'/templates/components/*.html.twig');
            self::assertIsArray($templates);

            foreach ($templates as $template) {
                preg_match_all("/'data-controller': '([a-z-]+)'|data-controller=\"([a-z-]+)\"/", (string) file_get_contents($template), $matches);

                foreach (array_filter([...$matches[1], ...$matches[2]], static fn (string $identifier): bool => '' !== $identifier) as $identifier) {
                    self::assertFileExists(\sprintf('%s/%s/assets/controllers/%s_controller.js', self::KIT, $directory, str_replace('-', '_', $identifier)));
                }
            }
        }
    }

    public function testEveryPropIsDocumentedAsTheToolkitAsks(): void
    {
        foreach (self::RECIPES as $directory => [, $name]) {
            $template = (string) file_get_contents(\sprintf('%s/%s/templates/components/%s.html.twig', self::KIT, $directory, $name));
            self::assertSame(1, preg_match('/\{%-? props(.*?)-?%\}/s', $template, $props), $name);

            $lines = array_values(array_filter(array_map(trim(...), explode("\n", $props[1])), static fn (string $line): bool => '' !== $line));

            for ($i = 0; $i < \count($lines); $i += 2) {
                self::assertMatchesRegularExpression('/^## \S+ \p{Lu}.*\.$/u', $lines[$i], $name.': a "## <type> <Description.>" line before each prop');
                self::assertMatchesRegularExpression('/^[a-z][A-Za-z]*( = .+)?,?$/', $lines[$i + 1] ?? '', $name);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $path): array
    {
        $json = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        /** @var array<string, mixed> $json */
        return $json;
    }
}
