<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Functional;

use Override;
use Phil\QrCodeBundle\PhilQrCodeBundle;
use Phil\QrCodeBundle\Tests\Functional\App\TestKernel;
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocksBundle\PhilSvgBlocksBundle;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(PhilQrCodeBundle::class)]
final class QrCodeBundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testDrawsCodesEveryWayInTwig(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/page');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertMatchesRegularExpression('{<div id="function"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 25" role="img" shape-rendering="crispEdges"><title>Hello</title><rect width="25" height="25" fill="\#ffffff"/><path fill="\#112233" d="M2 2h7}', $html);
        self::assertStringContainsString('<title>Gnome Café</title>', $html);
        self::assertStringContainsString('<image ', $html);
        self::assertStringContainsString('src="data:image/svg+xml;base64,', $html);
        self::assertStringContainsString('alt="HELLO"', $html);
        self::assertStringContainsString('src="data:image/png;base64,iVBORw0KGgo', $html);
        self::assertStringContainsString('<figure class="phil-qr-code"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg"', $html);
        self::assertStringContainsString('<figcaption class="phil-qr-code__caption"><a href="https://example.com/t/42" rel="noopener">https://example.com/t/42</a></figcaption>', $html);
        self::assertStringContainsString('<figure class="phil-qr-code ticket"><svg xmlns="http://www.w3.org/2000/svg" viewBox=', $html);
        self::assertStringContainsString('width="160" height="160" role="img" shape-rendering="crispEdges"><title>Join the network</title>', $html);
        self::assertStringContainsString('<figcaption class="phil-qr-code__caption">Gnome Café</figcaption>', $html);
        self::assertStringNotContainsString('tea-and-dice', $html);
        self::assertMatchesRegularExpression('{<div id="component-bare"><figure class="phil-qr-code"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 25" role="img" shape-rendering="crispEdges"><title>HELLO</title>.*?</svg></figure>\s*</div>}s', $html);
    }

    public function testServesTheCodesOfSignedAddresses(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/page');

        $html = (string) $client->getResponse()->getContent();
        self::assertSame(1, preg_match('{<img id="route" src="([^"]+)"}', $html, $svg));
        self::assertSame(1, preg_match('{<img id="route-png" src="([^"]+)"}', $html, $png));
        $svg = html_entity_decode($svg[1]);
        $png = html_entity_decode($png[1]);

        self::assertStringStartsWith('/qr-code.svg?', $svg);
        self::assertStringContainsString('_qr_signature=', $svg);
        self::assertStringStartsWith('https://example.com/qr-code.png?', $png);
        self::assertStringContainsString('logo=photo', $png);

        $client->request('GET', $svg);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'");
        $svgContent = $client->getResponse()->getContent();
        self::assertIsString($svgContent);
        self::assertStringContainsString('<title>https://example.com/t/42</title>', $svgContent);
        self::assertSame('3600', $client->getResponse()->headers->getCacheControlDirective('max-age'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('immutable'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('public'));
        $etag = (string) $client->getResponse()->getEtag();

        $client->request('GET', $svg, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);

        $client->request('GET', $png);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertStringStartsWith("\x89PNG", $client->getResponse()->getContent());

        $client->request('GET', str_replace('t%2F42', 't%2F43', $svg));
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/qr-code.svg?data=anything');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDrawsTheQrCodeBlocksOfUjoDocumentsWithItsDefaults(): void
    {
        if (!class_exists(PhilSvgBlocksBundle::class)) {
            self::markTestSkipped('phil/svg-blocks-bundle is not installed: the qrCode block of UJO documents is an optional integration.');
        }

        self::bootKernel();
        $markup = self::getContainer()->get(BlockMarkup::class);
        self::assertInstanceOf(BlockMarkup::class, $markup);

        $rendered = $markup->render(Document::fromArray(['blocks' => [['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'HELLO']]]]));
        $data = $rendered->document->blocks[0]->data['rendered'] ?? null;
        self::assertIsArray($data);
        $stored = $data['markup'] ?? null;
        self::assertIsString($stored);
        self::assertStringContainsString('fill="#112233"', $stored);
        self::assertStringContainsString('viewBox="0 0 25 25"', $stored);
    }
}
