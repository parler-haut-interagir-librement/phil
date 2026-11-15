<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Override;
use Phil\TermsBundle\Infrastructure\Twig\KitComponents;
use Phil\TermsBundle\Infrastructure\Twig\TermsTwig;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The Twig the pages are rendered with outside the bundle: TwigComponentBundle with the recipes of the PHIL UX kit in
 * the subdirectory `ui`, the templates and the translations of the bundle, and its routes at the root. One kernel per
 * locale, booted once, so that each `Kit` renders without building a container; in debug, so that a change of a
 * template, a translation or a recipe is seen at the next run. Its project directory is its own, under the temporary
 * directory.
 */
final class TemplatesKernel extends Kernel
{
    use MicroKernelTrait;

    /** @var array<string, Environment> */
    private static array $twigs = [];

    public function __construct(private readonly string $locale)
    {
        parent::__construct('templates_'.$locale, true);
    }

    /**
     * The Twig of the kernel of the locale.
     */
    public static function twig(string $locale): Environment
    {
        if (!isset(self::$twigs[$locale])) {
            $kernel = new self($locale);
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');
            \assert($container instanceof ContainerInterface);
            $twig = $container->get('twig');
            \assert($twig instanceof Environment);
            self::$twigs[$locale] = $twig;
        }

        return self::$twigs[$locale];
    }

    /**
     * Replaces the trait's own, which reads the `config/bundles.php` of the project directory.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/bundles.php';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return $this->root();
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
        return sys_get_temp_dir().'/phil-terms-bundle-templates/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $bundle = \dirname(__DIR__, 3);
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true],
            'default_locale' => $this->locale,
            'translator' => ['default_path' => $bundle.'/translations', 'fallbacks' => ['en']],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [KitRecipes::install(KitRecipes::PHIL, 'ui', $this->root()) => null, $bundle.'/templates' => 'PhilTerms']]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $services = $container->services();
        $services->set(KitComponents::class)->args(['ui']);
        $services->set(TermsTwig::class)->args([service(KitComponents::class)])->tag('twig.attribute_extension')->tag('twig.runtime');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes/pages.php');
        $routes->import(\dirname(__DIR__, 3).'/config/routes/admin.php');
    }
}
