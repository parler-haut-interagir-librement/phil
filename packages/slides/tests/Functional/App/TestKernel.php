<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Functional\App;

use LogicException;
use Override;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\Slides\Application\Contract\PresentedDecks;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Extra\Html\HtmlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator in `fr_CA` and AssetMapper,
 * TwigBundle, TwigComponentBundle with the recipe `Button` copied where `ux:install` would put it, SecurityBundle with
 * users over HTTP Basic — `speaker` holds the default presenter role, `organiser` another one, `visitor` neither —,
 * phil/ujo-bundle, which renders the slides, phil/paged-media-bundle with a print engine that hands the document back,
 * this bundle with a configuration of its own, its presenter routes under `/presenter` and the decks of TalkDecks.
 *
 * The recipe is that of the PHIL UX kit, in the subdirectory `ui`, or of the Shadcn kit of the Symfony UX Toolkit at
 * the root of the components in the environment `shadcn`, with Twig's HTML extension for its variants and the filters
 * of Tailwind's class merging returning the classes as they come. The environment `speaker_role` asks for the role of
 * `organiser`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string SHADCN = 'shadcn';

    public const string SPEAKER_ROLE = 'speaker_role';

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
        return self::SHADCN === $this->environment;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-slides-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The template of the recipe `Button`, where the kit's manifest copies it: the kit beside this package in a
     * monorepo, else among the development requirements of the monorepo's root, else among those of the package alone.
     */
    private function installRecipes(): string
    {
        $shadcn = $this->isShadcn();
        $root = $this->root().($shadcn ? '/shadcn' : '/phil');
        $target = $root.($shadcn ? '/templates/components' : '/templates/components/ui');
        $kit = $shadcn
            ? self::shadcnKit() ?? throw new LogicException('The Symfony UX Toolkit is not installed.') : array_find([__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'], static fn (string $directory): bool => is_file($directory.'/manifest.json')) ?? throw new LogicException('The PHIL UX kit is not installed.');

        if (!is_dir($target)) {
            mkdir($target, recursive: true);
        }

        copy($kit.'/button/templates/components/Button.html.twig', $target.'/Button.html.twig');

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
            'default_locale' => 'fr_CA',
            'translator' => ['fallbacks' => ['fr']],
            'asset_mapper' => ['paths' => []],
            'router' => ['utf8' => true],
        ]);

        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'speaker' => ['password' => 'speaker', 'roles' => ['ROLE_SLIDES_PRESENTER']],
                'organiser' => ['password' => 'organiser', 'roles' => ['ROLE_SPEAKER']],
                'visitor' => ['password' => 'visitor', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['stateless' => true, 'http_basic' => null, 'provider' => 'users']],
        ]);
        $container->extension('phil_slides', [
            'aspect_ratio' => '4:3',
            'fragment_class' => 'step',
            ...self::SPEAKER_ROLE === $this->environment ? ['presenter_role' => 'ROLE_SPEAKER'] : [],
        ]);

        $services = $container->services();
        $services->set(PrintEngine::class, HtmlPrintEngine::class);
        $services->set(PresentedDecks::class, TalkDecks::class)->autowire();

        if ($shadcn) {
            $services->set(HtmlExtension::class)->tag('twig.extension');
            $services->set(TailwindFilters::class)->tag('twig.attribute_extension')->tag('twig.runtime');
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/presenter');
    }
}
