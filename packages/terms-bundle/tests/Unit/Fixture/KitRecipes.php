<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use FilesystemIterator;
use LogicException;
use Phil\TermsBundle\PhilTermsBundle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The recipes the templates draw, copied where `ux:install` would put them: from the PHIL UX kit, in a subdirectory of
 * the components or at their root, or from the Shadcn kit of the Symfony UX Toolkit, at the root as its recipes call one
 * another by their root names, with the Label and Separator they call and a stand-in for the icons of Symfony UX Icons.
 */
final class KitRecipes
{
    public const string PHIL = 'ux-kit';

    public const string SHADCN = 'shadcn';

    private function __construct()
    {
    }

    /**
     * The directory of the Shadcn kit, `null` when the Symfony UX Toolkit is not installed.
     */
    public static function shadcnKit(): ?string
    {
        return array_find(
            [__DIR__.'/../../../../../vendor/symfony/ux-toolkit/kits/shadcn', __DIR__.'/../../../vendor/symfony/ux-toolkit/kits/shadcn'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        );
    }

    /**
     * Copies the recipes under `$root/templates/components` and gives the directory of templates that holds them.
     */
    public static function install(string $kit, string $subdirectory, string $root): string
    {
        $target = rtrim($root.'/templates/components/'.$subdirectory, '/');
        $recipes = PhilTermsBundle::RECIPES;

        if (self::SHADCN === $kit) {
            $directory = self::shadcnKit() ?? throw new LogicException('The Symfony UX Toolkit is not installed.');
            $recipes = [...$recipes, 'label', 'separator'];

            if (!is_dir($root.'/templates/components/ux')) {
                mkdir($root.'/templates/components/ux', recursive: true);
            }

            file_put_contents($root.'/templates/components/ux/icon.html.twig', '{% props name %}<svg data-icon="{{ name }}" aria-hidden="true"{{ attributes }}></svg>');
        } else {
            // The kit beside this package in the monorepo, else among the development requirements of the monorepo's
            // root, else among those of the package alone: a kit is a directory with its manifest.
            $directory = array_find(
                [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
                static fn (string $candidate): bool => is_file($candidate.'/manifest.json'),
            ) ?? __DIR__.'/../../../vendor/phil/ux-kit';
        }

        foreach ($recipes as $recipe) {
            $source = $directory.'/'.$recipe.'/templates/components';

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
                \assert($file instanceof SplFileInfo);
                $copy = $target.substr($file->getPathname(), \strlen($source));

                if (!is_dir(\dirname($copy))) {
                    mkdir(\dirname($copy), recursive: true);
                }

                copy($file->getPathname(), $copy);
            }
        }

        return $root.'/templates';
    }
}
