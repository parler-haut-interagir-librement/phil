<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit\Url;

use Phil\CssSanitizer\Url\AllowedFiles;
use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\CssSanitizer\Url\RefuseUrls;
use Phil\CssSanitizer\UrlKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefuseUrls::class)]
#[CoversClass(AllowedFiles::class)]
#[CoversClass(CallbackRewriter::class)]
#[CoversClass(UrlKind::class)]
final class RewritersTest extends TestCase
{
    public function testRefuseUrlsRefusesEverything(): void
    {
        self::assertNull(new RefuseUrls()->rewrite('a.png', UrlKind::Image));
        self::assertNull(new RefuseUrls()->rewrite('a.woff2', UrlKind::Font));
    }

    public function testAllowedFilesMapsNamesToPublicUrls(): void
    {
        $files = new AllowedFiles(['logo.png' => '/files/7/logo.png'], ['a.woff2' => '/files/7/a.woff2']);

        self::assertSame('/files/7/logo.png', $files->rewrite('logo.png', UrlKind::Image));
        self::assertSame('/files/7/logo.png', $files->rewrite('./logo.png', UrlKind::Image));
        self::assertSame('/files/7/a.woff2', $files->rewrite('a.woff2', UrlKind::Font));
        self::assertNull($files->rewrite('a.woff2', UrlKind::Image));
        self::assertNull($files->rewrite('logo.png', UrlKind::Font));
        self::assertNull($files->rewrite('../logo.png', UrlKind::Image));
        self::assertNull($files->rewrite('/logo.png', UrlKind::Image));
        self::assertNull($files->rewrite('https://example.com/logo.png', UrlKind::Image));
        self::assertNull($files->rewrite('x./logo.png', UrlKind::Image));
        self::assertNull(new AllowedFiles()->rewrite('logo.png', UrlKind::Image));
        self::assertNull($files->rewrite('logo.png', UrlKind::StyleSheet));

        $sheets = new AllowedFiles(['a.css' => '/i'], ['a.css' => '/f'], ['a.css' => '/files/7/a.css']);

        self::assertSame('/files/7/a.css', $sheets->rewrite('./a.css', UrlKind::StyleSheet));
        self::assertSame('/i', $sheets->rewrite('a.css', UrlKind::Image));
        self::assertSame('/f', $sheets->rewrite('a.css', UrlKind::Font));
        self::assertSame('/x', new AllowedFiles(['./a' => '/y', 'a' => '/x'])->rewrite('./a', UrlKind::Image));
    }

    public function testCallbackRewriterAsksTheClosure(): void
    {
        $rewriter = new CallbackRewriter(static fn (string $url, UrlKind $kind): ?string => 'font' === $kind->value ? null : '/cdn/'.$url);

        self::assertSame('/cdn/a.png', $rewriter->rewrite('a.png', UrlKind::Image));
        self::assertNull($rewriter->rewrite('a.woff2', UrlKind::Font));
    }
}
