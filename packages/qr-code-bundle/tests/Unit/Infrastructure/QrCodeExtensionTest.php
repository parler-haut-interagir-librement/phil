<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeController;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeUrls;
use Phil\QrCodeBundle\Infrastructure\Twig\QrCodeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(QrCodeExtension::class)]
final class QrCodeExtensionTest extends TestCase
{
    public function testDrawsCodesInTemplates(): void
    {
        $codes = new QrCodes();
        $twig = new Environment(new ArrayLoader([
            'page' => '{{ qr_code("HELLO") }}|{{ "HELLO"|qr_code }}|{{ "HELLO"|qr_code({output: "data_uri"}) }}|{{ qr_code("HELLO", {output: "png_data_uri"}) }}|{{ qr_code_label({type: "phone", number: "+1555"}) }}|{{ qr_code_url("HELLO") }}',
        ]), ['autoescape' => 'html']);
        $routes = new RouteCollection();
        $routes->add(QrCodeController::ROUTE, new Route('/qr-code.{format}'));

        $signer = new UriSigner('secret', '_qr_signature');
        $twig->addExtension(new QrCodeExtension($codes, new QrCodeUrls(new UrlGenerator($routes, new RequestContext()), $signer, $codes)));

        self::assertSame(implode('|', [
            $codes->svg('HELLO'),
            $codes->svg('HELLO'),
            $codes->dataUri('HELLO'),
            $codes->dataUri('HELLO', [], true),
            '+1555',
            htmlspecialchars(new QrCodeUrls(new UrlGenerator($routes, new RequestContext()), $signer, $codes)->url('HELLO'), \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5),
        ]), $twig->render('page'));
    }

    public function testRefusesAnUnknownOutput(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The output of qr_code() must be svg, data_uri or png_data_uri; got "jpeg".');

        new QrCodeExtension(new QrCodes())->qrCode('A', ['output' => 'jpeg']);
    }

    public function testNamesTheTypeOfAnOutputThatIsNoText(): void
    {
        $this->expectExceptionMessageIsOrContains('got int');

        new QrCodeExtension(new QrCodes())->qrCode('A', ['output' => 1]);
    }

    public function testNeedsTheRouteForAnAddress(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The route of qr_code_url() must be enabled in the configuration, route.enabled; got none.');

        new QrCodeExtension(new QrCodes())->url('A');
    }
}
