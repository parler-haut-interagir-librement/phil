<?php

declare(strict_types=1);

use Phil\CommentsBundle\Infrastructure\Http\PageController;
use Phil\CommentsBundle\Infrastructure\Http\PageForm;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The plain forms of the comments on a page, which work without JavaScript, imported by the application beside the
 * endpoints, behind its firewall:
 *
 *     # config/routes/phil_comments.yaml
 *     phil_comments_page:
 *         resource: '@PhilCommentsBundle/config/page_routes.php'
 *         prefix: /_comments
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(PageForm::ROUTE, '/page')->controller(PageController::class)->methods(['POST']);
};
