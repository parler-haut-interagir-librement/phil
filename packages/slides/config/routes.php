<?php

declare(strict_types=1);

use Phil\Slides\Infrastructure\Http\PresenterController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The presenter view of a deck and the PDF of the deck with its notes, imported by the application under the prefix of
 * its choice, behind its firewall; both ask for the role `phil_slides.presenter_role`. The audience page stays a route
 * of the application's own.
 *
 *     phil_slides_presenter:
 *         resource: '@PhilSlidesBundle/config/routes.php'
 *         prefix: /presenter
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(PresenterController::VIEW_ROUTE, '/{deck}')
        ->controller([PresenterController::class, 'view'])
        ->requirements(['deck' => PresenterController::DECK])
        ->methods(['GET', 'HEAD']);
    $routes->add(PresenterController::NOTES_PDF_ROUTE, '/{deck}/notes.pdf')
        ->controller([PresenterController::class, 'notesPdf'])
        ->requirements(['deck' => PresenterController::DECK])
        ->methods(['GET', 'HEAD']);
};
