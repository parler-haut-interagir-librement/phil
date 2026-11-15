<?php

declare(strict_types=1);

use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Infrastructure\Http\ClickController;
use Phil\NewsletterBundle\Infrastructure\Http\PreviewController;
use Phil\NewsletterBundle\Infrastructure\Http\SubscriptionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The public pages of the newsletter, mounted by the application under a prefix of its choice:
 *
 *     # config/routes/phil_newsletter.yaml
 *     phil_newsletter:
 *         resource: '@PhilNewsletterBundle/config/routes.php'
 *         prefix: /newsletter
 *
 * The preview of the Markdown editor answers only the role the configuration names.
 */
return static function (RoutingConfigurator $routes): void {
    $token = ['token' => '[A-Za-z0-9_-]{1,490}\.[A-Za-z0-9_-]{22}'];

    $routes->add('phil_newsletter_subscribe', '/subscribe/{list}')
        ->controller([SubscriptionController::class, 'subscribe'])
        ->requirements(['list' => '[a-z0-9][a-z0-9_-]{0,63}'])
        ->methods(['GET', 'POST']);

    $routes->add(NewsletterUrls::CONFIRM, '/confirm/{token}')
        ->controller([SubscriptionController::class, 'confirm'])
        ->requirements($token)
        ->methods(['GET', 'POST']);

    $routes->add(NewsletterUrls::UNSUBSCRIBE, '/unsubscribe/{token}')
        ->controller([SubscriptionController::class, 'unsubscribe'])
        ->requirements($token)
        ->methods(['GET', 'POST']);

    $routes->add(NewsletterUrls::PREFERENCES, '/preferences/{token}')
        ->controller([SubscriptionController::class, 'preferences'])
        ->requirements($token)
        ->methods(['GET', 'POST']);

    $routes->add(NewsletterUrls::CLICK, '/c/{token}')
        ->controller(ClickController::class)
        ->requirements($token)
        ->methods(['GET', 'HEAD']);

    $routes->add('phil_newsletter_preview', '/preview')
        ->controller(PreviewController::class)
        ->methods(['POST']);
};
