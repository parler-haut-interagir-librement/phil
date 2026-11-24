<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit;

use Phil\SeoCheck\Exception\InvalidPage;
use Phil\SeoCheck\Exception\SeoCheckException;
use Phil\SeoCheck\RenderedPage;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderedPage::class)]
#[CoversClass(InvalidPage::class)]
final class RenderedPageTest extends TestCase
{
    public function testHeadersAreKeptByNameInLowerCase(): void
    {
        $page = new RenderedPage(Url::fromString('https://example.org/'), '<p>', 200, ['X-Robots-Tag' => 'noindex', 'x-robots-tag' => ['nofollow', 'none'], 'Content-Language' => 'fr'], 'retail');

        self::assertSame(['x-robots-tag' => ['noindex', 'nofollow', 'none'], 'content-language' => ['fr']], $page->headers);
        self::assertSame(['noindex', 'nofollow', 'none'], $page->header('X-ROBOTS-TAG'));
        self::assertSame([], $page->header('Link'));
        self::assertSame('retail', $page->channel);
        self::assertSame(200, $page->status);
    }

    #[TestWith([100])]
    #[TestWith([599])]
    public function testAStatusIsBetween100And599(int $status): void
    {
        self::assertSame($status, new RenderedPage(Url::fromString('https://example.org/'), '', $status)->status);
    }

    #[TestWith([99])]
    #[TestWith([600])]
    public function testAnotherStatusIsRefused(int $status): void
    {
        $this->expectException(InvalidPage::class);
        $this->expectExceptionMessageIsOrContains($status.' is not an HTTP status code: a status is between 100 and 599.');

        new RenderedPage(Url::fromString('https://example.org/'), '', $status);
    }

    #[TestWith(['X Robots'])]
    #[TestWith(["X-Robots\n"])]
    #[TestWith([''])]
    public function testAHeaderNameIsAToken(string $name): void
    {
        try {
            new RenderedPage(Url::fromString('https://example.org/'), '', 200, [$name => 'noindex']);
            self::fail('The header name is refused.');
        } catch (InvalidPage $invalidPage) {
            self::assertInstanceOf(SeoCheckException::class, $invalidPage);
            self::assertSame(\sprintf('"%s" is not an HTTP header name.', $name), $invalidPage->getMessage());
        }
    }

    public function testEveryTokenCharacterIsAllowedInAName(): void
    {
        $name = "!#$%&'*+.^_`|~09AZaz-";

        self::assertSame(['v'], new RenderedPage(Url::fromString('https://example.org/'), '', 200, [$name => 'v'])->header($name));
    }

    public function testAPageAnswersWith200ByDefault(): void
    {
        self::assertSame(200, new RenderedPage(Url::fromString('https://example.org/'), '')->status);
    }
}
