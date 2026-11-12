<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Functional\App;

use Override;
use Phil\SocialCardsBundle\Application\Contract\PageFetcher;
use Phil\SocialCardsBundle\Application\FetchedPage;
use Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard;
use Phil\SocialCardsBundle\Tests\Support\FakeFetcher;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger on the synchronous transport, Twig and its
 * components, a card template in the fixture font, a page whose template sets its values, previews two links and shows a
 * kept preview, the endpoint of pasted links behind a rate limiter of two, and a fetcher of fixed pages in place of the
 * network.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-social-cards-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-social-cards-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    public static function cardDirectory(): string
    {
        return sys_get_temp_dir().'/phil-social-cards-bundle-test/'.hash('xxh128', __DIR__).'/cards';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['default_uri' => 'https://example.com'],
            'cache' => ['pools' => ['test.limiter_pool' => ['adapter' => 'cache.adapter.filesystem']]],
            'rate_limiter' => ['link_preview' => ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 hour', 'cache_pool' => 'test.limiter_pool', 'lock_factory' => null]],
            'messenger' => [
                'transports' => ['cards' => 'sync://'],
                'routing' => [GenerateSocialCard::class => 'cards'],
            ],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_social_cards', [
            'site_name' => 'Le site',
            'twitter_site' => '@lesite',
            'preview' => ['limiter' => 'limiter.link_preview'],
            'cards' => [
                'directory' => self::cardDirectory(),
                'templates' => ['default' => ['title_font' => __DIR__.'/../../Fixtures/card-font.ttf', 'accent' => '#e11d48']],
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(PageController::class)->public()->tag('controller.service_arguments');
        $services->set('logger', NullLogger::class);
        $services->set('test.fetched_post', FetchedPage::class)->args(['<head><meta property="og:title" content="Un billet"><meta property="og:site_name" content="Ailleurs"><meta property="og:description" content="Le résumé"><meta property="og:image" content="/i.png"></head>', 'https://example.org/post']);
        $services->set(FakeFetcher::class)->args([['https://example.org/post' => new Reference('test.fetched_post')]]);
        $services->alias(PageFetcher::class, FakeFetcher::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilSocialCardsBundle/config/routes.php');
        $routes->import('@PhilSocialCardsBundle/config/link_preview_routes.php');
        $routes->add('post', '/blog/post')->controller(PageController::class);
    }
}
