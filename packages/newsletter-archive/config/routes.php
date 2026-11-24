<?php

declare(strict_types=1);

use Phil\NewsletterArchive\Infrastructure\Http\ArchiveController;
use Phil\NewsletterArchive\Infrastructure\Routing\RouterIssueUrls;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The public pages of the archive, mounted by the application under a prefix of its choice:
 *
 *     # config/routes/phil_newsletter_archive.yaml
 *     phil_newsletter_archive:
 *         resource: '@PhilNewsletterArchiveBundle/config/routes.php'
 *         prefix: /newsletter/archive
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(RouterIssueUrls::INDEX, '/')
        ->controller([ArchiveController::class, 'index'])
        ->methods(['GET', 'HEAD']);

    $routes->add(RouterIssueUrls::LINK, '/shared/{token}')
        ->controller([ArchiveController::class, 'link'])
        ->requirements(['token' => '[A-Za-z0-9_-]{43}'])
        ->methods(['GET', 'HEAD']);

    $routes->add(RouterIssueUrls::ISSUE, '/{slug}')
        ->controller([ArchiveController::class, 'issue'])
        ->requirements(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
        ->methods(['GET', 'HEAD']);
};
