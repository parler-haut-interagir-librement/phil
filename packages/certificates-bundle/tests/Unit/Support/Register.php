<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Phil\CertificatesBundle\Application\CertificatePdf;
use Phil\CertificatesBundle\Infrastructure\Http\RegisterController;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Phil\CertificatesBundle\PhilCertificatesBundle;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The register's controller over memory, without a kernel: the bundle's routes under `/certificates` on example.com,
 * its templates and translations in a Twig environment of their own, and a PDF drawn without Gotenberg.
 */
final class Register
{
    private function __construct()
    {
    }

    public static function urlGenerator(): UrlGenerator
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $routes->addPrefix('/certificates');
        $routes->add(StatusListUrls::ROUTE, new Route('/open-badges/status/{list}', requirements: ['list' => StatusListUrls::LIST]));

        return new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https'));
    }

    public static function urls(): RoutedCertificateUrls
    {
        $generator = self::urlGenerator();

        return new RoutedCertificateUrls($generator, new StatusListUrls($generator));
    }

    public static function controller(InMemoryCourses $courses, InMemoryCertificates $certificates, ClockInterface $clock): RegisterController
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilCertificates');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(Translations::translator()));
        $twig->addExtension(new RoutingExtension(self::urlGenerator()));
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);

        $pdf = new CertificatePdf(new PagedMedia(), self::urls(), Translations::translator(), Kit::issuer(), PageSize::letter(), bake: false);

        return new RegisterController($courses, $certificates, $pdf, $twig, self::urlGenerator(), $clock, PhilCertificatesBundle::DEFAULT_LAYOUT);
    }
}
