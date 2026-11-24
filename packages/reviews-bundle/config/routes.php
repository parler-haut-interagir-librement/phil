<?php

declare(strict_types=1);

use Phil\ReviewsBundle\Infrastructure\Http\ReviewsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the reviews, imported by the application under the prefix of its choice:
 *
 *     phil_reviews:
 *         resource: '@PhilReviewsBundle/config/routes.php'
 *         prefix: /reviews
 *
 * The forms are given the rules of phil/rate-limit-bundle the configuration names under "rate_limit".
 */
return static function (RoutingConfigurator $routes): void {
    $review = ['review' => ReviewsController::UUID];
    $subject = ['type' => ReviewsController::TYPE, 'id' => ReviewsController::SUBJECT];

    $routes->add(ReviewsController::VOTE_ROUTE, '/review/{review}/vote')
        ->controller([ReviewsController::class, 'vote'])
        ->requirements($review)
        ->methods(['POST']);
    $routes->add(ReviewsController::RESPOND_ROUTE, '/review/{review}/response')
        ->controller([ReviewsController::class, 'respond'])
        ->requirements($review)
        ->methods(['POST']);
    $routes->add(ReviewsController::CONTEST_ROUTE, '/review/{review}/contest')
        ->controller([ReviewsController::class, 'contest'])
        ->requirements($review)
        ->methods(['POST']);
    $routes->add(ReviewsController::PAGE_ROUTE, '/{type}/{id}')
        ->controller([ReviewsController::class, 'page'])
        ->requirements($subject)
        ->methods(['GET']);
    $routes->add(ReviewsController::WRITE_ROUTE, '/{type}/{id}')
        ->controller([ReviewsController::class, 'write'])
        ->requirements($subject)
        ->methods(['POST']);
};
