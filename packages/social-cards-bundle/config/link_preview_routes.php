<?php

declare(strict_types=1);

use Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The previews of the links pasted in a form, imported apart, behind the firewall of the members who write:
 *
 *     # config/routes/phil_social_cards.yaml
 *     phil_social_cards_link_preview:
 *         resource: '@PhilSocialCardsBundle/config/link_preview_routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(LinkPreviewController::ROUTE, '/social-cards/link-preview')
        ->controller(LinkPreviewController::class)
        ->methods(['GET']);
};
