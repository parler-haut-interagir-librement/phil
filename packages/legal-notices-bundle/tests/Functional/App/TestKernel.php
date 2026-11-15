<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\LegalNoticesBundle\PhilLegalNoticesBundle;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\FixedSchedule;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\Html\HtmlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator, Twig, TwigComponentBundle with the
 * recipes of a kit copied where `ux:install` would put them — in the subdirectory of the application's Toolkit
 * configuration, the Toolkit itself stood for by its parameter —, a legal notice whose French body is a Markdown file, a
 * privacy policy with the retention periods of a schedule of phil/privacy-bundle and consent categories, and the pages
 * under a prefix per locale.
 *
 * The recipes are those of the PHIL UX kit, or of the Shadcn kit of the Symfony UX Toolkit with Twig's HTML extension
 * for their variants and the filter of Tailwind's classes returning the classes as they come.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string PHIL = 'phil';

    public const string SHADCN = 'shadcn';

    /**
     * @param self::PHIL|self::SHADCN $kit
     */
    public function __construct(string $environment, bool $debug, private readonly string $subdirectory = 'ui', private readonly string $kit = self::PHIL)
    {
        parent::__construct($environment, $debug);
    }

    /**
     * The directory of a kit, among the development requirements of the monorepo's root, else among those of the
     * package alone; `null` when it is not installed.
     *
     * @param self::PHIL|self::SHADCN $kit
     */
    public static function kitDirectory(string $kit): ?string
    {
        $path = self::SHADCN === $kit ? 'symfony/ux-toolkit/kits/shadcn' : 'phil/ux-kit';

        return array_find(
            [__DIR__.'/../../../../../vendor/'.$path, __DIR__.'/../../../vendor/'.$path],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        );
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
        return sys_get_temp_dir().'/phil-legal-notices-bundle-test/'.hash('xxh128', __DIR__.$this->kit.'/'.$this->subdirectory);
    }

    /**
     * The templates of the recipes the bundle declares, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = rtrim($this->root().'/templates/components/'.$this->subdirectory, '/');
        $kit = self::kitDirectory($this->kit) ?? throw new RuntimeException(\sprintf('The %s kit is not installed.', $this->kit));

        foreach (PhilLegalNoticesBundle::RECIPES as $recipe) {
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

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);
        $container->parameters()->set('.ux_toolkit.components.subdirectory', '' === $this->subdirectory ? null : $this->subdirectory);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_legal_notices', [
            'identity' => [
                'publisher_name' => 'Example Inc.',
                'publisher_address' => "1 Main Street\nMontréal",
                'publication_director' => 'Jo Smith',
                'contact_email' => 'hello@example.com',
                'host_name' => 'Example <Hosting>',
                'host_address' => 'Toronto',
                'host_contact' => 'support@hosting.example',
                'dpo_name' => 'Jo Smith',
                'dpo_contact' => 'privacy@example.com',
            ],
            'documents' => [
                'legal_notice' => [
                    ['number' => 1, 'format' => 'markdown', 'published_at' => '2026-01-01', 'bodies' => ['en' => 'Published by **${publisher_name}**.'], 'files' => ['fr' => '%kernel.project_dir%/legal/notice.fr.md']],
                    ['number' => 2, 'published_at' => '2026-02-01', 'effective_at' => '2099-01-01', 'bodies' => ['en' => 'A later version.'], 'summaries' => ['en' => 'A new host.']],
                    ['number' => 3, 'state' => 'draft', 'effective_at' => '2099-02-01', 'bodies' => ['en' => 'A draft.']],
                ],
                'privacy_policy' => [
                    ['number' => 1, 'published_at' => '2026-01-01', 'bodies' => ['en' => 'Write to ${dpo_contact}. Audience: ${consent.analytics}.']],
                ],
            ],
            'retention' => ['source' => FixedSchedule::class, 'labels' => ['R-001' => ['en' => 'Your account', 'fr' => 'Votre compte']]],
            'consent' => ['categories' => [
                'necessary' => ['necessary' => true, 'labels' => ['en' => 'Necessary', 'fr' => 'Nécessaires'], 'cookies' => ['PHPSESSID']],
                'analytics' => ['labels' => ['en' => 'Audience measurement'], 'descriptions' => ['en' => 'Pages seen, without a profile.'], 'cookies' => ['_pk_id.*', '_pk_ses.*', '_pk_ref.*']],
            ]],
        ]);

        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(FixedSchedule::class);

        if (self::SHADCN === $this->kit) {
            $container->services()->set(HtmlExtension::class)->tag('twig.extension');
            $container->services()->set(TailwindFilters::class)->tag('twig.attribute_extension')->tag('twig.runtime');
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilLegalNoticesBundle/config/routes.php')->prefix(['en' => '', 'fr' => '/fr']);
    }
}
