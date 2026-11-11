<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    /**
     * The directories of the package the archive leaves out; `src/` stays, for Composer to install the browser test
     * classes other kits reuse.
     */
    private const array LEFT_OUT = ['doc', 'tests'];

    /**
     * The recipes older than the convention of the Toolkit on documented blocks, their blocks described in their header.
     */
    private const array RECIPES_WITH_NAMED_BLOCKS = ['coming-soon'];

    /**
     * The recipes naming a controller of a bundle, which the Toolkit does not install and so does not rewrite.
     */
    private const array RECIPES_NAMING_A_CONTROLLER_OF_A_BUNDLE = ['reaction-picker'];

    private const array RECIPES = [
        'accordion' => ['component', 'Accordion'],
        'activity-calendar' => ['component', 'ActivityCalendar'],
        'alert' => ['component', 'Alert'],
        'alert-dialog' => ['component', 'AlertDialog'],
        'animated-button' => ['component', 'AnimatedButton'],
        'animated-headline' => ['component', 'AnimatedHeadline'],
        'aspect-ratio' => ['component', 'AspectRatio'],
        'autosave-status' => ['component', 'AutosaveStatus'],
        'avatar' => ['component', 'Avatar'],
        'badge' => ['component', 'Badge'],
        'before-after' => ['component', 'BeforeAfter'],
        'breadcrumb' => ['component', 'Breadcrumb'],
        'button' => ['component', 'Button'],
        'button-group' => ['component', 'ButtonGroup'],
        'card' => ['component', 'Card'],
        'carousel' => ['component', 'Carousel'],
        'chart' => ['component', 'Chart'],
        'chart-js' => ['component', 'ChartJs'],
        'checkbox' => ['component', 'Checkbox'],
        'collapsible' => ['component', 'Collapsible'],
        'color-picker' => ['component', 'ColorPicker'],
        'coming-soon' => ['block', 'ComingSoon'],
        'copy-button' => ['component', 'CopyButton'],
        'date-picker' => ['component', 'DatePicker'],
        'dialog' => ['component', 'Dialog'],
        'dropdown-menu' => ['component', 'DropdownMenu'],
        'empty' => ['component', 'Empty'],
        'field' => ['component', 'Field'],
        'file-input' => ['component', 'FileInput'],
        'illustration' => ['component', 'Illustration'],
        'input' => ['component', 'Input'],
        'item' => ['component', 'Item'],
        'kbd' => ['component', 'Kbd'],
        'label' => ['component', 'Label'],
        'login-form' => ['block', 'LoginForm'],
        'logo' => ['component', 'Logo'],
        'native-select' => ['component', 'NativeSelect'],
        'number-input' => ['component', 'NumberInput'],
        'pagination' => ['component', 'Pagination'],
        'popover' => ['component', 'Popover'],
        'progress' => ['component', 'Progress'],
        'progress-ring' => ['component', 'ProgressRing'],
        'qr-code' => ['component', 'QrCode'],
        'radio-group' => ['component', 'RadioGroup'],
        'rating' => ['component', 'Rating'],
        'reaction-button' => ['component', 'ReactionButton'],
        'reaction-picker' => ['component', 'ReactionPicker'],
        'relative-time' => ['component', 'RelativeTime'],
        'scroll-area' => ['component', 'ScrollArea'],
        'select' => ['component', 'Select'],
        'separator' => ['component', 'Separator'],
        'sheet' => ['component', 'Sheet'],
        'signup-form' => ['block', 'SignupForm'],
        'skeleton' => ['component', 'Skeleton'],
        'sparkline' => ['component', 'Sparkline'],
        'spinner' => ['component', 'Spinner'],
        'stepper' => ['component', 'Stepper'],
        'switch' => ['component', 'Switch'],
        'table' => ['component', 'Table'],
        'tabs' => ['component', 'Tabs'],
        'tag' => ['component', 'Tag'],
        'textarea' => ['component', 'Textarea'],
        'toggle' => ['component', 'Toggle'],
        'toggle-group' => ['component', 'ToggleGroup'],
        'tooltip' => ['component', 'Tooltip'],
        'tree' => ['component', 'Tree'],
    ];

    public function testTheKitManifestHasTheFieldsOfTheToolkit(): void
    {
        $manifest = $this->json(self::KIT.'/manifest.json');

        foreach (['name', 'description', 'license', 'homepage'] as $field) {
            self::assertIsString($manifest[$field] ?? null, $field);
        }

        self::assertSame('PHIL UX', $manifest['name']);
        self::assertSame('MIT', $manifest['license']);
        self::assertIsString($manifest['icon'] ?? null);
        self::assertFileExists(self::KIT.'/'.$manifest['icon']);
    }

    public function testEveryDirectoryIsARecipeOrLeftOutOfTheArchiveButTheTestClasses(): void
    {
        $directories = glob(self::KIT.'/*', \GLOB_ONLYDIR);
        self::assertIsArray($directories);
        $names = array_map(basename(...), $directories);

        // An installation of the package on its own adds `vendor/`, which the repository ignores.
        self::assertSame(array_keys(self::RECIPES), array_values(array_diff($names, [...self::PACKAGE_DIRECTORIES, 'vendor', 'var'])));

        $attributes = (string) file_get_contents(self::KIT.'/.gitattributes');

        foreach (self::LEFT_OUT as $directory) {
            self::assertStringContainsString('/'.$directory.' export-ignore', $attributes);
        }

        self::assertStringNotContainsString('/src export-ignore', $attributes);
        $tests = glob(self::KIT.'/src/Test/*');
        self::assertIsArray($tests);
        self::assertSame(['BrowserTestCase.php', 'RecipesPage.php'], array_map(basename(...), $tests));
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

    public function testEachControllerATemplateNamesIsCopiedByItsRecipeOrARecipeItDependsOn(): void
    {
        foreach (array_keys(self::RECIPES) as $directory) {
            $homes = [$directory, ...$this->recipeDependencies($directory)];

            foreach ($this->templates($directory) as $template) {
                preg_match_all("/'data-controller': '([a-z-]+)'|data-controller=\"([a-z-]+)\"/", (string) file_get_contents($template), $matches);

                foreach (array_filter([...$matches[1], ...$matches[2]], static fn (string $identifier): bool => '' !== $identifier) as $identifier) {
                    $file = str_replace('-', '_', $identifier).'_controller.js';
                    $found = array_filter($homes, static fn (string $home): bool => is_file(\sprintf('%s/%s/assets/controllers/%s', self::KIT, $home, $file)));

                    self::assertNotSame([], $found, $directory.' names the controller '.$identifier);
                }
            }
        }
    }

    public function testEveryPropOfEveryTemplateIsDocumentedAsTheToolkitAsks(): void
    {
        foreach (array_keys(self::RECIPES) as $directory) {
            foreach ($this->templates($directory) as $template) {
                if (1 !== preg_match('/\{%-? props(.*?)-?%\}/s', (string) file_get_contents($template), $props)) {
                    continue;
                }

                $lines = array_values(array_filter(array_map(trim(...), explode("\n", $props[1])), static fn (string $line): bool => '' !== $line));

                for ($i = 0; $i < \count($lines); $i += 2) {
                    self::assertMatchesRegularExpression('/^## \S+ \p{Lu}.*\.$/u', $lines[$i], $template.': a "## <type> <Description.>" line before each prop');
                    self::assertMatchesRegularExpression('/^[a-z][A-Za-z]*( = .+)?,?$/', $lines[$i + 1] ?? '', $template);
                }
            }
        }
    }

    public function testEveryBlockIsDocumented(): void
    {
        foreach (array_keys(self::RECIPES) as $directory) {
            foreach ($this->templates($directory) as $template) {
                $source = (string) file_get_contents($template);
                preg_match_all('/\{%-? block \w+|block\(outerBlocks\.\w+\)/', $source, $blocks);

                if ([] === $blocks[0] || \in_array($directory, self::RECIPES_WITH_NAMED_BLOCKS, true)) {
                    continue;
                }

                self::assertStringContainsString('{##', $source, $template);
            }
        }
    }

    /**
     * Words live in props or blocks, never in the markup of a template: the application translates them.
     */
    public function testNoTemplateWritesAWordOfItsOwn(): void
    {
        foreach (array_keys(self::RECIPES) as $directory) {
            foreach ($this->templates($directory) as $template) {
                $source = (string) preg_replace(['/\{#.*?#\}/s', '/\{%.*?%\}/s', '/\{\{.*?\}\}/s', '/"[^"]*"/s', '/<[^>]*>/s', '/&#?\w+;/'], '', (string) file_get_contents($template));

                self::assertDoesNotMatchRegularExpression('/\p{L}/u', $source, $template);
                self::assertDoesNotMatchRegularExpression('/aria-(label|roledescription)="[^{"]/', (string) file_get_contents($template), $template);
            }
        }
    }

    /**
     * The Toolkit rewrites the names of components and controllers in the copied files when `ux_toolkit` sets a
     * subdirectory: it reads literals, so a name composed at run time would escape it.
     */
    public function testComponentAndControllerNamesAreLiteralsTheToolkitCanRewrite(): void
    {
        foreach (array_diff(array_keys(self::RECIPES), self::RECIPES_NAMING_A_CONTROLLER_OF_A_BUNDLE) as $directory) {
            foreach ($this->templates($directory) as $template) {
                $source = (string) file_get_contents($template);

                self::assertDoesNotMatchRegularExpression("/'data-'\\s*~|component\\(\\s*[^'\"]/", $source, $template);
                self::assertDoesNotMatchRegularExpression('/data-[a-z-]*\{\{/', $source, $template);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function recipeDependencies(string $directory): array
    {
        $dependencies = $this->json(self::KIT.'/'.$directory.'/manifest.json')['dependencies'] ?? [];
        self::assertIsArray($dependencies);
        $recipes = $dependencies['recipe'] ?? [];
        self::assertIsArray($recipes);

        return array_values(array_filter($recipes, is_string(...)));
    }

    /**
     * @return list<string>
     */
    private function templates(string $directory): array
    {
        $paths = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::KIT.'/'.$directory.'/templates/components', FilesystemIterator::SKIP_DOTS)) as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            $paths[] = $file->getPathname();
        }

        return $paths;
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

    public function testEveryClassATemplateWritesIsOneOfTheStylesheet(): void
    {
        $sheet = self::KIT.'/../ux-css/dist/phil-ux.css';

        if (!is_file($sheet)) {
            self::markTestSkipped('phil/ux-css is not next to this kit.');
        }

        $css = (string) file_get_contents($sheet);
        $templates = glob(self::KIT.'/*/templates/components{/,/*/}*.html.twig', \GLOB_BRACE);
        self::assertIsArray($templates);
        self::assertNotSame([], $templates);

        foreach ($templates as $template) {
            preg_match_all('/\\b(?:is|has)[A-Z][A-Za-z]+\\b/', (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($template)), $classes);

            foreach (array_unique($classes[0]) as $class) {
                self::assertMatchesRegularExpression('/\\.'.$class.'\\b/', $css, basename($template).' writes '.$class);
            }
        }
    }
}
