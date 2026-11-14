<?php

declare(strict_types=1);

use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Infrastructure\Http\RegisterController;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The public register, imported by the application where it wants it:
 *
 *     # config/routes/phil_certificates.yaml
 *     phil_certificates:
 *         resource: '@PhilCertificatesBundle/config/routes.php'
 *         prefix: /certificates
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(RegisterController::INDEX_ROUTE, '/')
        ->controller([RegisterController::class, 'index'])
        ->methods(['GET']);
    $routes->add(RoutedCertificateUrls::COURSE_ROUTE, '/courses/{slug}')
        ->controller([RegisterController::class, 'course'])
        ->requirements(['slug' => Course::SLUG])
        ->methods(['GET']);
    $routes->add(RoutedCertificateUrls::CERTIFICATE_ROUTE, '/{code}')
        ->controller([RegisterController::class, 'certificate'])
        ->requirements(['code' => VerificationCode::PATTERN])
        ->methods(['GET']);
    $routes->add(RegisterController::PDF_ROUTE, '/{code}/certificate.pdf')
        ->controller([RegisterController::class, 'pdf'])
        ->requirements(['code' => VerificationCode::PATTERN])
        ->methods(['GET']);
    $routes->add(RegisterController::CREDENTIAL_ROUTE, '/{code}/credential')
        ->controller([RegisterController::class, 'credential'])
        ->requirements(['code' => VerificationCode::PATTERN])
        ->methods(['GET']);
};
