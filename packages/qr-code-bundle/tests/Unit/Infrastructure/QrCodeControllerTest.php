<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use Phil\QrCodeBundle\Application\Logos;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeController;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeUrls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(QrCodeController::class)]
#[CoversClass(QrCodeUrls::class)]
final class QrCodeControllerTest extends TestCase
{
    private UriSigner $signer;

    private QrCodes $codes;

    private QrCodeUrls $urls;

    protected function setUp(): void
    {
        $this->signer = new UriSigner('secret', '_qr_signature');
        $this->codes = new QrCodes(logos: new Logos(['brand' => ['path' => __DIR__.'/../../Fixtures/logo.png', 'zone' => 0.2]]));
        $routes = new RouteCollection();
        $routes->add(QrCodeController::ROUTE, new Route('/qr-code.{format}'));

        $this->urls = new QrCodeUrls(new UrlGenerator($routes, new RequestContext('', 'GET', 'example.com', 'https')), $this->signer, $this->codes);
    }

    public function testWritesSignedAddresses(): void
    {
        $path = $this->urls->url('HELLO');
        $absolute = $this->urls->url(['type' => 'phone', 'number' => '+1555'], 'png', 'brand', true);

        self::assertMatchesRegularExpression('{^/qr-code\.svg\?_qr_signature=[\w-]+&data=HELLO$}', $path);
        self::assertMatchesRegularExpression('{^https://example\.com/qr-code\.png\?_qr_signature=[\w-]+&data=tel%3A%2B1555&logo=brand$}', $absolute);
        self::assertTrue($this->signer->check('https://example.com'.$path));
        self::assertTrue($this->signer->check($absolute));
    }

    public function testRefusesAnUnknownFormat(): void
    {
        $this->expectExceptionMessageIsOrContains('The format must be svg or png; got "gif".');

        $this->urls->url('HELLO', 'gif');
    }

    public function testServesTheSvgOfASignedAddress(): void
    {
        $response = new QrCodeController($this->codes, $this->signer, 60)(Request::create('https://example.com'.$this->urls->url('HELLO')), 'svg');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->codes->svg('HELLO'), $response->getContent());
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame("default-src 'none'; img-src data:; style-src 'unsafe-inline'", $response->headers->get('Content-Security-Policy'));
        self::assertSame('60', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('immutable'));
        self::assertSame('"'.hash('xxh128', "svg\0\0HELLO").'"', $response->getEtag());
    }

    public function testServesThePngOfASignedAddressWithItsLogo(): void
    {
        $response = new QrCodeController($this->codes, $this->signer)(Request::create($this->urls->url('HELLO', 'png', 'brand', true)), 'png');

        self::assertSame($this->codes->png('HELLO', ['logo' => 'brand']), $response->getContent());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame('31536000', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('"'.hash('xxh128', "png\0brand\0HELLO").'"', $response->getEtag());
    }

    public function testAnswersNotModifiedToItsOwnTag(): void
    {
        $request = Request::create($this->urls->url('HELLO', absolute: true));
        $request->headers->set('If-None-Match', '"'.hash('xxh128', "svg\0\0HELLO").'"');

        $response = new QrCodeController($this->codes, $this->signer)($request, 'svg');

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testFindsNoCodeAtAnAddressItDidNotSign(): void
    {
        foreach ([
            'https://example.com/qr-code.svg?data=HELLO',
            str_replace('HELLO', 'HELLA', $this->urls->url('HELLO', absolute: true)),
            $this->signer->sign('https://example.com/qr-code.svg?data[]=HELLO'),
            $this->signer->sign('https://example.com/qr-code.svg?data=HELLO&logo[]=x'),
            $this->signer->sign('https://example.com/qr-code.svg'),
            $this->signer->sign('https://example.com/qr-code.svg?data=HELLO&logo=nope'),
            $this->signer->sign('https://example.com/qr-code.svg?data='.str_repeat('a', 3000)),
        ] as $url) {
            try {
                new QrCodeController($this->codes, $this->signer)(Request::create($url), 'svg');
                self::fail($url.' is not found.');
            } catch (NotFoundHttpException $notFoundHttpException) {
                self::assertSame('No such QR code.', $notFoundHttpException->getMessage());
            }
        }
    }
}
