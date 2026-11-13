<?php

declare(strict_types=1);

use Phil\CommentsBundle\Infrastructure\Http\CommentsController;
use Phil\CommentsBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The comments controller's endpoints, imported by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_comments.yaml
 *     phil_comments:
 *         resource: '@PhilCommentsBundle/config/routes.php'
 *         prefix: /_comments
 */
return static function (RoutingConfigurator $routes): void {
    $id = '[A-Za-z0-9_-]{1,36}';

    $routes->add(Routes::NAMES['threads'], '/threads')->controller([CommentsController::class, 'threads'])->methods(['GET']);
    $routes->add(Routes::NAMES['open'], '/threads')->controller([CommentsController::class, 'open'])->methods(['POST']);

    foreach (['reply', 'resolve', 'reopen', 'accept', 'reject'] as $action) {
        $routes->add(Routes::NAMES[$action], '/threads/{thread}/'.$action)->controller([CommentsController::class, $action])->methods(['POST'])->requirements(['thread' => $id]);
    }

    foreach (['edit', 'delete'] as $action) {
        $routes->add(Routes::NAMES[$action], '/comments/{comment}/'.$action)->controller([CommentsController::class, $action])->methods(['POST'])->requirements(['comment' => $id]);
    }
};
