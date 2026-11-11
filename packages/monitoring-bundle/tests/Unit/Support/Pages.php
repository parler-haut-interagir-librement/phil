<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The bundle's routes and templates without a kernel: the health endpoints under `/_health`, the queues under
 * `/admin/queues`, on example.com.
 */
final class Pages
{
    private function __construct()
    {
    }

    public static function urlGenerator(): UrlGenerator
    {
        $loader = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config/routes'));
        $routes = $loader->load('health.php');
        $routes->addPrefix('/_health');

        $queues = $loader->load('queues.php');
        $queues->addPrefix('/admin/queues');

        $routes->addCollection($queues);

        return new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https'));
    }

    public static function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilMonitoring');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new RoutingExtension(self::urlGenerator()));

        return $twig;
    }
}
