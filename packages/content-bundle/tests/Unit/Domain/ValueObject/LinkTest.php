<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\ValueObject;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\LocaleDimensions\Locale;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Link::class)]
final class LinkTest extends TestCase
{
    public function testALinkToAContentRootNamesItsKeyAndItsIdentifier(): void
    {
        $link = Link::toContent('article', '0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b');

        self::assertTrue($link->isToContent());
        self::assertSame('article', $link->resourceKey);
        self::assertSame('0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b', $link->resourceId);
        self::assertNull($link->locale);
        self::assertNull($link->url);
    }

    public function testALinkToAContentRootMayPinALocale(): void
    {
        $locale = Locale::fromString('fr_CA');

        self::assertSame($locale, Link::toContent('page', '42', $locale)->locale);
    }

    public function testALinkToAUrlNamesNothingElse(): void
    {
        $url = Url::fromString('https://example.org/');
        $link = Link::toUrl($url);

        self::assertFalse($link->isToContent());
        self::assertSame($url, $link->url);
        self::assertNull($link->resourceKey);
        self::assertNull($link->resourceId);
        self::assertNull($link->locale);
    }

    #[DataProvider('validResourceKeys')]
    public function testAResourceKeyIsKept(string $resourceKey): void
    {
        self::assertSame($resourceKey, Link::toContent($resourceKey, '1')->resourceKey);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validResourceKeys(): iterable
    {
        yield 'one letter' => ['a'];
        yield 'digits and underscores' => ['news_item2'];
        yield 'the longest' => ['a'.str_repeat('b', 63)];
    }

    #[DataProvider('invalidResourceKeys')]
    public function testAResourceKeyIsRefused(string $resourceKey): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('is not a resource key');

        Link::toContent($resourceKey, '1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidResourceKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'a leading digit' => ['1article'];
        yield 'uppercase' => ['Article'];
        yield 'a hyphen' => ['news-item'];
        yield 'one character too many' => ['a'.str_repeat('b', 64)];
        yield 'a leading space' => [' article'];
        yield 'a trailing space' => ['article '];
        yield 'a trailing line break' => ["article\n"];
    }

    #[DataProvider('validResourceIds')]
    public function testAResourceIdentifierIsKept(string $resourceId): void
    {
        self::assertSame($resourceId, Link::toContent('article', $resourceId)->resourceId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validResourceIds(): iterable
    {
        yield 'an integer' => ['1'];
        yield 'a ULID' => ['01JBX6Z4M3Q2W5E8R7T9Y0U1I2'];
        yield 'every allowed character' => ['AZaz09_-'];
        yield 'the longest' => [str_repeat('a', 64)];
    }

    #[DataProvider('invalidResourceIds')]
    public function testAResourceIdentifierIsRefused(string $resourceId): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('is not a resource identifier');

        Link::toContent('article', $resourceId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidResourceIds(): iterable
    {
        yield 'empty' => [''];
        yield 'a slash' => ['a/b'];
        yield 'a space inside' => ['a b'];
        yield 'one character too many' => [str_repeat('a', 65)];
        yield 'a leading space' => [' 42'];
        yield 'a trailing space' => ['42 '];
        yield 'a trailing line break' => ["42\n"];
    }
}
