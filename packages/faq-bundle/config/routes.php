<?php

declare(strict_types=1);

use Phil\FaqBundle\Infrastructure\Http\FaqController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The page of questions, its votes and its searches without answer, mounted by the application under a prefix of its
 * choice:
 *
 *     # config/routes/phil_faq.yaml
 *     phil_faq:
 *         resource: '@PhilFaqBundle/config/routes.php'
 *         prefix: /faq
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(FaqController::PAGE_ROUTE, '/')
        ->controller([FaqController::class, 'page'])
        ->methods(['GET', 'HEAD']);

    $routes->add(FaqController::VOTE_ROUTE, '/vote/{anchor}')
        ->controller([FaqController::class, 'vote'])
        ->requirements(['anchor' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
        ->methods(['POST']);

    $routes->add(FaqController::UNANSWERED_ROUTE, '/unanswered')
        ->controller([FaqController::class, 'unanswered'])
        ->methods(['POST']);
};
