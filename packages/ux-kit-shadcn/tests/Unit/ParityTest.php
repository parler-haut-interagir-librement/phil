<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The recipes of the PHIL UX kit that the Shadcn kit of the Toolkit does not offer, and its `Carousel` on native scroll
 * snapping, under the names, props, blocks and Stimulus controllers of the PHIL UX kit: only the markup's classes
 * differ, and the recipes of the Shadcn kit that the PHIL UX kit installs with them, which this kit draws inline.
 * Checked against the PHIL UX kit installed among the development requirements.
 */
#[CoversNothing]
final class ParityTest extends TestCase
{
    private const string SHADCN = __DIR__.'/../..';

    /**
     * The PHIL UX kit among the development requirements: those of the monorepo's root, or those of this package alone.
     */
    private const array PHIL_CANDIDATES = [__DIR__.'/../../../../vendor/phil/ux-kit', __DIR__.'/../../vendor/phil/ux-kit'];

    private string $phil;

    /**
     * Every recipe of this kit, so that a recipe forgotten here or there shows.
     */
    private const array RECIPES = [
        'activity-calendar',
        'animated-button',
        'animated-headline',
        'autosave-status',
        'before-after',
        'carousel',
        'celebration',
        'chart-js',
        'chart',
        'color-picker',
        'coming-soon',
        'copy-button',
        'figure',
        'file-input',
        'floating-label',
        'gallery',
        'hero',
        'hotspots',
        'illustration',
        'image',
        'lightbox',
        'login-form',
        'logo',
        'number-input',
        'progress-ring',
        'qr-code',
        'rating',
        'reaction-button',
        'reaction-picker',
        'relative-time',
        'signup-form',
        'sparkline',
        'stepper',
        'sticky-header',
        'tag',
        'theme-switcher',
        'timeline',
        'tree',
        'video',
    ];

    #[Override]
    protected function setUp(): void
    {
        $phil = array_find(self::PHIL_CANDIDATES, static fn (string $directory): bool => is_file($directory.'/manifest.json'));

        if (null === $phil) {
            self::markTestSkipped('The PHIL UX kit is not installed.');
        }

        $this->phil = $phil;
    }

    public function testThisKitHoldsExactlyItsOwnRecipes(): void
    {
        self::assertSame(self::RECIPES, $this->recipes(self::SHADCN));
    }

    public function testThePhilKitHasEachOfThem(): void
    {
        self::assertSame(self::RECIPES, array_values(array_intersect($this->recipes($this->phil), self::RECIPES)));
    }

    /**
     * The same manifests, but for the recipes of the Shadcn kit a recipe of the PHIL UX kit depends on, `button` for
     * `Carousel`: the Toolkit installs the recipes a manifest names from the same kit, so this kit draws them inline.
     */
    public function testTheManifestsAreTheSame(): void
    {
        foreach (self::RECIPES as $recipe) {
            self::assertSame($this->manifest($this->phil.'/'.$recipe.'/manifest.json', ownOnly: true), $this->manifest(self::SHADCN.'/'.$recipe.'/manifest.json', ownOnly: false), $recipe);
        }
    }

    public function testTheCarouselLeavesOutTheButtonOfTheShadcnKitAlone(): void
    {
        $phil = $this->manifest($this->phil.'/carousel/manifest.json', ownOnly: false);
        $dependencies = $phil['dependencies'] ?? null;

        self::assertIsArray($dependencies);
        self::assertSame(['button'], $dependencies['recipe'] ?? null);
        self::assertSame($this->manifest(self::SHADCN.'/carousel/manifest.json', ownOnly: false), $this->manifest($this->phil.'/carousel/manifest.json', ownOnly: true));
    }

    public function testThePropsAndBlocksAreTheSame(): void
    {
        self::assertSame($this->templates($this->phil), $this->templates(self::SHADCN));

        foreach ($this->templates($this->phil) as $path) {
            $phil = (string) file_get_contents($this->phil.'/'.$path);
            $shadcn = (string) file_get_contents(self::SHADCN.'/'.$path);

            self::assertSame($this->props($phil), $this->props($shadcn), $path);
            self::assertSame($this->blocks($phil), $this->blocks($shadcn), $path);
            self::assertSame($this->stimulus($phil), $this->stimulus($shadcn), $path);
        }
    }

    public function testTheControllersAreTheSameFiles(): void
    {
        foreach (self::RECIPES as $recipe) {
            $controllers = glob($this->phil.'/'.$recipe.'/assets/controllers/*_controller.js');
            self::assertIsArray($controllers);

            foreach ($controllers as $controller) {
                self::assertFileEquals($controller, self::SHADCN.'/'.substr($controller, \strlen($this->phil) + 1));
            }
        }
    }

    /**
     * A manifest, without the recipes it depends on that are not recipes of this kit when `$ownOnly` is set.
     *
     * @return array<array-key, mixed>
     */
    private function manifest(string $path, bool $ownOnly): array
    {
        $manifest = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest, $path);

        $dependencies = $manifest['dependencies'] ?? null;

        if (!$ownOnly || !\is_array($dependencies) || !\is_array($dependencies['recipe'] ?? null)) {
            return $manifest;
        }

        $recipes = array_values(array_filter($dependencies['recipe'], static fn (mixed $recipe): bool => \in_array($recipe, self::RECIPES, true)));

        if ([] === $recipes) {
            unset($dependencies['recipe']);
        } else {
            $dependencies['recipe'] = $recipes;
        }

        $manifest['dependencies'] = $dependencies;

        return $manifest;
    }

    /**
     * @return list<string>
     */
    private function recipes(string $kit): array
    {
        $manifests = glob($kit.'/*/manifest.json');
        self::assertIsArray($manifests);

        return array_map(static fn (string $path): string => basename(\dirname($path)), $manifests);
    }

    /**
     * The templates of the recipes compared, those of this kit being kept to them by the first test.
     *
     * @return list<string>
     */
    private function templates(string $kit): array
    {
        $templates = [];

        foreach (self::RECIPES as $recipe) {
            $paths = glob($kit.'/'.$recipe.'/templates/components/*.html.twig');
            self::assertIsArray($paths);

            foreach ($paths as $path) {
                $templates[] = substr($path, \strlen($kit) + 1);
            }
        }

        return $templates;
    }

    private function props(string $template): string
    {
        self::assertSame(1, preg_match('/\{%- props.*?-%\}/s', $template, $props));

        return $props[0];
    }

    /**
     * @return list<string>
     */
    private function blocks(string $template): array
    {
        preg_match_all('/\{%-? block (\w+)/', $template, $blocks);

        return $blocks[1];
    }

    /**
     * The Stimulus attributes, which the shared controller reads, or the controller of a bundle.
     *
     * @return list<string>
     */
    private function stimulus(string $template): array
    {
        preg_match_all("/data-(?:animated-headline|before-after|carousel|celebration|color-picker|copy-button|file-input|lightbox|number-input|relative-time|sticky-header|tag|theme-switcher|tree)-[a-z-]+|data-phil--forms-autosave--autosave-target=\"[A-Za-z]+\"|data-phil--reactions-bundle--picker-target=\"[A-Za-z]+\"|data-action=\"[^\"]+\"|'data-controller': '[a-z-]+'/", $template, $attributes);

        return $attributes[0];
    }
}
