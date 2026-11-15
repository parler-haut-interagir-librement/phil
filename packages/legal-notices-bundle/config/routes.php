<?php

declare(strict_types=1);

use Phil\LegalNotices\DocumentType;
use Phil\LegalNoticesBundle\Infrastructure\Http\DocumentController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The public pages of the documents: `/legal` and `/privacy`, their `/history` and each version at `/v{number}`.
 * The application mounts them at the root, or under a prefix per locale:
 *
 *     # config/routes/phil_legal_notices.yaml
 *     phil_legal_notices:
 *         resource: '@PhilLegalNoticesBundle/config/routes.php'
 *         prefix: { en: '', fr: '/fr' }
 */
return static function (RoutingConfigurator $routes): void {
    foreach ([DocumentType::LegalNotice->value => '/legal', DocumentType::PrivacyPolicy->value => '/privacy'] as $type => $path) {
        $document = DocumentType::from($type);

        $routes->add(DocumentController::route($document), $path)
            ->controller([DocumentController::class, 'current'])
            ->defaults(['type' => $type])
            ->methods(['GET']);

        $routes->add(DocumentController::route($document, 'history'), $path.'/history')
            ->controller([DocumentController::class, 'history'])
            ->defaults(['type' => $type])
            ->methods(['GET']);

        $routes->add(DocumentController::route($document, 'version'), $path.'/v{number}')
            ->controller([DocumentController::class, 'version'])
            ->defaults(['type' => $type])
            ->requirements(['number' => '[1-9]\d{0,5}'])
            ->methods(['GET']);
    }
};
