<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Override;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Node;
use Phil\UxKit\Infrastructure\StructuredData\ImageJsonLd;
use Phil\UxKit\Tests\Functional\App\SourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;

#[CoversClass(ImageJsonLd::class)]
final class ImageJsonLdTest extends TestCase
{
    public function testAnImageIsWrittenAsItsOwnScriptWithoutNonce(): void
    {
        self::assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"ImageObject","digitalSourceType":"https://schema.org/DigitalCaptureDigitalSource","contentUrl":"https://www.example.org/images/bridge.jpg","caption":"The bridge \u003Cat\u003E dusk","width":800,"height":600,"license":"https://creativecommons.org/licenses/by/4.0/","creditText":"Photo: Ana Lima"}]}</script>',
            $this->jsonLd()->script([
                'src' => '/images/bridge.jpg',
                'width' => 800,
                'height' => '600',
                'caption' => 'The bridge <at> dusk',
                'credit' => 'Photo: Ana Lima',
                'licenseHref' => 'https://creativecommons.org/licenses/by/4.0/',
                'creation' => 'digitalCapture',
            ]),
        );
    }

    public function testTheNodeJoinsTheGraphOfThePageAndNothingIsWritten(): void
    {
        $graph = new class implements NodeCollector {
            /** @var list<Node> */
            public array $nodes = [];

            #[Override]
            public function add(Node ...$nodes): void
            {
                array_push($this->nodes, ...array_values($nodes));
            }
        };

        self::assertSame('', $this->jsonLd($graph)->script(['src' => '/a.jpg']));
        self::assertCount(1, $graph->nodes);
        self::assertSame(['@type' => 'ImageObject', 'contentUrl' => 'https://www.example.org/a.jpg'], $graph->nodes[0]->toArray());
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function addresses(): iterable
    {
        yield 'a path of the site' => ['/images/a.jpg', 'https://www.example.org/images/a.jpg'];
        yield 'a path relative to the page' => ['images/a.jpg', 'https://www.example.org/blog/images/a.jpg'];
        yield 'an address of another host' => ['https://images.example.com/a.jpg', 'https://images.example.com/a.jpg'];
        yield 'from its two slashes' => ['//images.example.com/a.jpg', 'https://images.example.com/a.jpg'];
        yield 'from two backslashes' => ['\\\\images.example.com/a.jpg', 'https://images.example.com/a.jpg'];
        yield 'with tabs and spaces' => [" \t/images/\na.jpg ", 'https://www.example.org/images/a.jpg'];
        yield 'normalised by phil/url' => ['HTTPS://Images.Example.com:443/a/../b.jpg', 'https://images.example.com/b.jpg'];
        yield 'a space, which ends an address' => ['https://images.example.com/a b.jpg', null];
        yield 'a tab in its path' => ["/im\tages/a.jpg", 'https://www.example.org/images/a.jpg'];
        yield 'a line feed in its path' => ["/im\nages/a.jpg", 'https://www.example.org/images/a.jpg'];
        yield 'a carriage return in its path' => ["/im\rages/a.jpg", 'https://www.example.org/images/a.jpg'];
        yield 'a colon in its path' => ['/images/a:b.jpg', 'https://www.example.org/images/a:b.jpg'];
        yield 'a scheme in capitals' => ['JAVASCRIPT:alert(1)', null];
        yield 'an inline image' => ['data:image/png;base64,iVBORw0KGgo=', null];
        yield 'an IP address' => ['https://192.0.2.1/a.jpg', null];
        yield 'another scheme' => ['ftp://images.example.com/a.jpg', null];
        yield 'nothing' => ['', null];
    }

    #[DataProvider('addresses')]
    public function testTheAddressIsMadeAbsoluteAndReadByPhilUrl(string $src, ?string $contentUrl): void
    {
        $node = $this->jsonLd()->node(['src' => $src]);

        self::assertSame($contentUrl, $node?->toArray()['contentUrl'] ?? null);
    }

    public function testWithoutARequestAPathIsDescribedByNothing(): void
    {
        self::assertNull(new ImageJsonLd()->node(['src' => '/a.jpg']));
        self::assertNull(new ImageJsonLd(new UrlHelper(new RequestStack()))->node(['src' => '/a.jpg']));
        self::assertSame('', new ImageJsonLd()->script(['src' => '/a.jpg']));
        self::assertSame('https://images.example.com/a.jpg', new ImageJsonLd()->node(['src' => 'https://images.example.com/a.jpg'])?->toArray()['contentUrl'] ?? null);
    }

    public function testLocalhostIsAHostOnlyWhenAllowed(): void
    {
        self::assertNull(new ImageJsonLd()->node(['src' => 'https://app.localhost/a.jpg']));
        self::assertSame('https://app.localhost/a.jpg', new ImageJsonLd(null, true)->node(['src' => 'https://app.localhost/a.jpg'])?->toArray()['contentUrl'] ?? null);
    }

    public function testNotAStringIsNoAddress(): void
    {
        self::assertNull($this->jsonLd()->node([]));
        self::assertNull($this->jsonLd()->node(['src' => 42]));
        self::assertSame(['@type' => 'ImageObject', 'contentUrl' => 'https://www.example.org/a.jpg'], $this->jsonLd()->node(['src' => '/a.jpg', 'licenseHref' => ['x']])?->toArray());
    }

    public function testTheLicenseAddressIsMadeAbsoluteOrLeftOut(): void
    {
        self::assertSame('https://www.example.org/license', $this->jsonLd()->node(['src' => '/a.jpg', 'licenseHref' => '/license'])?->toArray()['license'] ?? null);
        self::assertArrayNotHasKey('license', $this->jsonLd()->node(['src' => '/a.jpg', 'licenseHref' => 'javascript:alert(1)'])?->toArray() ?? []);
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function sizes(): iterable
    {
        yield 'an integer' => [800, 800];
        yield 'its digits' => ['800', 800];
        yield 'zero' => [0, null];
        yield 'a negative integer' => [-1, null];
        yield 'digits after a zero' => ['0800', null];
        yield 'a decimal' => ['8.5', null];
        yield 'digits and a line break' => ["800\n", null];
        yield 'nothing' => [null, null];
    }

    #[DataProvider('sizes')]
    public function testASizeIsAPositiveInteger(mixed $size, mixed $expected): void
    {
        $node = $this->jsonLd()->node(['src' => '/a.jpg', 'width' => $size, 'height' => $size])?->toArray() ?? [];

        self::assertSame($expected, $node['width'] ?? null);
        self::assertSame($expected, $node['height'] ?? null);
    }

    public function testAnEmptyCaptionOrCreditIsLeftOut(): void
    {
        self::assertSame(['@type' => 'ImageObject', 'contentUrl' => 'https://www.example.org/a.jpg'], $this->jsonLd()->node(['src' => '/a.jpg', 'caption' => ' ', 'credit' => '', 'creation' => 42])?->toArray());
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function creations(): iterable
    {
        yield 'a photograph' => ['digitalCapture', 'DigitalCaptureDigitalSource'];
        yield 'a computational capture' => ['computationalCapture', 'MultiFrameComputationalCaptureDigitalSource'];
        yield 'created with generative AI' => ['trainedAlgorithmicMedia', 'TrainedAlgorithmicMediaDigitalSource'];
        yield 'edited with generative AI' => ['compositeWithTrainedAlgorithmicMedia', 'CompositeWithTrainedAlgorithmicMediaDigitalSource'];
        yield 'an enum whose value is a code' => [SourceType::Generated, 'TrainedAlgorithmicMediaDigitalSource'];
        yield 'a code schema.org has no member for' => ['humanEdits', null];
        yield 'a retired code' => ['softwareImage', null];
        yield 'a code in the wrong case' => ['DigitalCapture', null];
        yield 'an unknown code' => ['photo', null];
    }

    #[DataProvider('creations')]
    public function testTheCreationIsAMemberOfTheEnumerationOfSchemaOrg(mixed $creation, ?string $member): void
    {
        self::assertSame(null === $member ? null : 'https://schema.org/'.$member, $this->jsonLd()->node(['src' => '/a.jpg', 'creation' => $creation])?->toArray()['digitalSourceType'] ?? null);
    }

    private function jsonLd(?NodeCollector $graph = null): ImageJsonLd
    {
        $requests = new RequestStack([Request::create('https://www.example.org/blog/post')]);

        return new ImageJsonLd(new UrlHelper($requests), false, $graph);
    }
}
