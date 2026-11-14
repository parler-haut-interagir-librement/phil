<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional\App;

use FilesystemIterator;
use LogicException;
use Override;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Extra\Html\HtmlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle with the
 * translator, TwigComponentBundle with the recipes of the kit copied where `ux:install` would put them, the bundle, a
 * counting method of its own and a listener of the ballots cast.
 *
 * The recipes are those of the PHIL UX kit, or of the Shadcn kit of the Symfony UX Toolkit in the environments ending
 * in `shadcn`, with Twig's HTML extension for their variants, the filters of Tailwind's class merging returning the
 * classes as they come, and an icon drawn empty. The browser environments keep their polls in a SQLite file.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['button', 'checkbox', 'field', 'native-select', 'progress', 'radio-group'];

    public const string SHADCN = 'shadcn';

    public const string BROWSER = 'browser';

    public const string BROWSER_SHADCN = 'browser_shadcn';

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

    public function isShadcn(): bool
    {
        return str_ends_with($this->environment, self::SHADCN);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-poll-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-poll-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $shadcn = $this->isShadcn();
        $root = sys_get_temp_dir().'/phil-poll-bundle-test/'.hash('xxh128', __DIR__).($shadcn ? '/shadcn' : '/phil');
        $target = $root.'/templates/components/ui';
        $kit = $shadcn ? self::shadcnKit() ?? throw new LogicException('The Symfony UX Toolkit is not installed.') : array_find([__DIR__.'/../../../../ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'], static fn (string $directory): bool => is_file($directory.'/manifest.json')) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        if ($shadcn) {
            // The Shadcn recipes call the `Label` of their kit and the icons of Symfony UX Icons by their root names.
            $target = $root.'/templates/components';
            if (!is_dir($target.'/ux')) {
                mkdir($target.'/ux', recursive: true);
            }

            file_put_contents($target.'/ux/icon.html.twig', '{% props name %}<svg data-icon="{{ name }}" aria-hidden="true"{{ attributes }}></svg>');
        }

        foreach ($shadcn ? [...self::RECIPES, 'label'] : self::RECIPES as $recipe) {
            $source = $kit.'/'.$recipe.'/templates/components';

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

    private function configureContainer(ContainerConfigurator $container): void
    {
        $shadcn = $this->isShadcn();
        $container->parameters()->set('.ux_toolkit.components.subdirectory', $shadcn ? '' : 'ui');
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $browser = str_starts_with($this->environment, self::BROWSER);
        $container->extension('doctrine', ['dbal' => ['url' => $browser ? 'sqlite:///'.$this->getCacheDir().'/polls.sqlite' : 'sqlite:///:memory:']]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(FirstPreferences::class);
        $services->set(RecordedEvents::class)->public();

        if ($shadcn) {
            $services->set(HtmlExtension::class);
            $services->set(TailwindFilters::class);
        }
    }
}
