<?php

declare(strict_types=1);

use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Infrastructure\Http\FeedController;
use Phil\FeedBundle\Infrastructure\Routing\RouterFeedUrls;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Every feed in every format, imported by the application, under a prefix it chooses:
 *
 *     # config/routes/phil_feed.yaml
 *     phil_feed:
 *         resource: '@PhilFeedBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(RouterFeedUrls::ROUTE, '/feeds/{name}.{format}')
        ->controller(FeedController::class)
        ->requirements(['name' => Feeds::NAME_PATTERN, 'format' => 'atom|rss|json'])
        ->methods(['GET', 'HEAD']);
};
