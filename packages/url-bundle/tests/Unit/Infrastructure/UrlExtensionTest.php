<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit\Infrastructure;

use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\UrlBundle\Infrastructure\Twig\UrlExtension;
use Phil\UrlBundle\Tests\Unit\Fixtures\RegistryUrlType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlExtension::class)]
final class UrlExtensionTest extends TestCase
{
    public function testAnAddressIsDisplayed(): void
    {
        $extension = new UrlExtension();

        self::assertSame('example.com/shop', $extension->display('https://www.example.com/shop/'));
        self::assertSame('example.com', $extension->display(Url::fromString('https://example.com')));
        self::assertSame('facebook.com/shop', $extension->display(TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/shop')));
        self::assertSame('not an address', $extension->display('not an address'));
        self::assertSame('', $extension->display(null));
        self::assertSame('', $extension->display(''));
    }

    public function testLocalhostIsReadOnlyWhenAllowed(): void
    {
        self::assertSame('http://localhost:8000/', new UrlExtension()->display('http://localhost:8000/'));
        self::assertNull(new UrlExtension()->type('http://localhost:8000/'));
        self::assertSame('localhost:8000', new UrlExtension(allowLocalhost: true)->display('http://localhost:8000/'));
        self::assertSame(KnownUrlType::Website, new UrlExtension(allowLocalhost: true)->type('https://app.localhost/'));
        self::assertNull(new UrlExtension(allowLocalhost: true)->type('http://127.0.0.1/'));
    }

    public function testTheKindOfAnAddressIsFound(): void
    {
        $extension = new UrlExtension();

        self::assertSame(KnownUrlType::YouTube, $extension->type('https://youtu.be/abc'));
        self::assertSame(KnownUrlType::Website, $extension->type(Url::fromString('https://example.com')));
        self::assertSame(RegistryUrlType::Registry, $extension->type('https://registry.example/', RegistryUrlType::class));
        self::assertNull($extension->type('https://example.com/', KnownUrlType::Facebook));
        self::assertSame(KnownUrlType::Website, $extension->type(TypedUrl::of(KnownUrlType::Website, 'https://facebook.com/x'), KnownUrlType::Facebook));
        self::assertNull($extension->type('nope'));
        self::assertNull($extension->type(null));
    }

    public function testAKindHasItsLabel(): void
    {
        $extension = new UrlExtension();

        self::assertSame('X (Twitter)', $extension->label(KnownUrlType::X));
        self::assertSame('Registry', $extension->label(RegistryUrlType::Registry));
        self::assertSame('', $extension->label(null));
    }
}
