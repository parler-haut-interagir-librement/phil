<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Tests\Unit;

use InvalidArgumentException;
use Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed;
use Phil\PagedMediaWeasyprint\Internal\AssetInlining;
use Phil\PagedMediaWeasyprint\LocalAssets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(LocalAssets::class)]
#[CoversClass(AssetInlining::class)]
#[CoversClass(WeasyPrintFailed::class)]
final class LocalAssetsTest extends TestCase
{
    private string $base;

    private string $assets;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/phil-weasyprint-assets-'.bin2hex(random_bytes(8));
        $this->assets = $this->base.'/assets';
        mkdir($this->assets.'/sub', 0o700, true);
        file_put_contents($this->assets.'/logo.png', 'logo');
        file_put_contents($this->assets.'/sub/face.woff2', 'face');
        file_put_contents($this->assets.'/press.icc', 'icc');
        file_put_contents($this->assets.'/press.ICM', 'icm');
        file_put_contents($this->assets.'/UPPER.JPG', 'upper');
        file_put_contents($this->assets.'/with space.png', 'space');
        file_put_contents($this->assets.'/.env', 'SECRET=1');
        file_put_contents($this->assets.'/notes.txt', 'notes');
        file_put_contents($this->base.'/outside.png', 'outside');
        symlink($this->base.'/outside.png', $this->assets.'/link.png');
        mkdir($this->assets.'/folder.png');
        file_put_contents($this->assets.'/a:logo.png', 'scheme');
        file_put_contents($this->assets.'/B:logo.png', 'scheme');
        mkdir($this->base.'/assets-sibling');
        file_put_contents($this->base.'/assets-sibling/logo.png', 'sibling');
    }

    protected function tearDown(): void
    {
        chmod($this->assets.'/logo.png', 0o600);
        new Filesystem()->remove($this->base);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function elements(): iterable
    {
        yield 'img' => ['<img src="logo.png">', '<img src="data:image/png;base64,bG9nbw==">'];
        yield 'embed' => ['<embed src="logo.png">', '<embed src="data:image/png;base64,bG9nbw==">'];
        yield 'object' => ['<object data="logo.png"></object>', '<object data="data:image/png;base64,bG9nbw=="></object>'];
        yield 'SVG href' => ['<svg><image href="logo.png"></image></svg>', '<svg><image href="data:image/png;base64,bG9nbw=="></image></svg>'];
        yield 'SVG xlink' => ['<svg><image xlink:href="logo.png"></image></svg>', '<svg><image xlink:href="data:image/png;base64,bG9nbw=="></image></svg>'];
        yield 'style attribute' => ['<p style="background: url(logo.png)">x</p>', '<p style="background: url(&quot;data:image/png;base64,bG9nbw==&quot;)">x</p>'];
        yield 'subdirectory' => ['<img src="sub/../logo.png">', '<img src="data:image/png;base64,bG9nbw==">'];
        yield 'dotted start' => ['<img src="./logo.png">', '<img src="data:image/png;base64,bG9nbw==">'];
        yield 'percent-encoded' => ['<img src="with%20space.png">', '<img src="data:image/png;base64,c3BhY2U=">'];
        yield 'capitals' => ['<img src="UPPER.JPG">', '<img src="data:image/jpeg;base64,dXBwZXI=">'];
    }

    #[DataProvider('elements')]
    public function testAFileOfTheDirectoryIsWrittenIntoTheElement(string $body, string $expected): void
    {
        self::assertStringContainsString($expected, $this->inline($body));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stylesheets(): iterable
    {
        yield 'bare' => ['a { background: url(sub/face.woff2) }'];
        yield 'double quotes' => ['a { background: url("sub/face.woff2") }'];
        yield 'single quotes' => ["a { background: url('sub/face.woff2') }"];
        yield 'spaces' => ['a { background: url(  sub/face.woff2  ) }'];
        yield 'capitals' => ['a { background: URL(sub/face.woff2) }'];
    }

    #[DataProvider('stylesheets')]
    public function testAStylesheetTakesItsFonts(string $css): void
    {
        self::assertStringContainsString('url("data:font/woff2;base64,ZmFjZQ==")', $this->inline('<style>'.$css.'</style>'));
    }

    public function testAColourProfileIsTaken(): void
    {
        $css = '@color-profile --press { src: url("press.icc"); components: c, m, y, k } @color-profile --old { src: url(press.ICM) }';

        $html = $this->inline('<style>'.$css.'</style>');

        self::assertStringContainsString('src: url("data:application/vnd.iccprofile;base64,aWNj")', $html);
        self::assertStringContainsString('src: url("data:application/vnd.iccprofile;base64,aWNt")', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'parent directory' => ['../outside.png'];
        yield 'a directory whose name starts the same' => ['../assets-sibling/logo.png'];
        yield 'a file whose name looks like a scheme' => ['a:logo.png'];
        yield 'a file whose name looks like a scheme in capitals' => ['B:logo.png'];
        yield 'a directory named as an image' => ['folder.png'];
        yield 'encoded parent directory' => ['%2E%2E/outside.png'];
        yield 'link out of the directory' => ['link.png'];
        yield 'absolute path' => ['/etc/hosts'];
        yield 'backslash root' => ['\\etc\\hosts'];
        yield 'file address' => ['file:///etc/hosts'];
        yield 'remote address' => ['https://example.com/logo.png'];
        yield 'protocol-relative address' => ['//example.com/logo.png'];
        yield 'query' => ['logo.png?x=1'];
        yield 'fragment' => ['logo.png#x'];
        yield 'NUL byte' => ['logo.png%00.png'];
        yield 'missing file' => ['absent.png'];
        yield 'a directory' => ['sub'];
        yield 'the directory itself' => ['.'];
        yield 'empty' => [''];
        yield 'an extension not taken' => ['notes.txt'];
        yield 'no extension' => ['.env'];
    }

    #[DataProvider('refused')]
    public function testAReferenceNotToTakeIsLeftAsItIsWritten(string $reference): void
    {
        $html = '<!doctype html><html><head></head><body><img src="'.$reference.'"></body></html>';

        self::assertSame($html, new LocalAssets($this->assets)->inline($html));
    }

    public function testAnUnreadableFileIsLeftAsItIsWritten(): void
    {
        chmod($this->assets.'/logo.png', 0);

        if (is_readable($this->assets.'/logo.png')) {
            self::markTestSkipped('This user reads every file, whatever its mode.');
        }

        $html = '<img src="logo.png">';

        self::assertSame($html, new LocalAssets($this->assets)->inline($html));
    }

    public function testAnElementThatShowsNoFileIsLeftAlone(): void
    {
        self::assertStringContainsString('<a href="logo.png">x</a>', $this->inline('<img src="logo.png"><a href="logo.png">x</a>'));
    }

    public function testATextThatLooksLikeAStylesheetIsLeftAlone(): void
    {
        self::assertStringContainsString('<p>url(logo.png)</p>', $this->inline('<img src="logo.png"><p>url(logo.png)</p>'));
    }

    public function testAStylesheetThatNamesNothingToTakeKeepsItsText(): void
    {
        $inlined = $this->inline('<img src="logo.png"><style>a { background: url(absent.png) }</style><p style="color: red">x</p>');

        self::assertStringContainsString('<style>a { background: url(absent.png) }</style>', $inlined);
        self::assertStringContainsString('<p style="color: red">x</p>', $inlined);
    }

    public function testADocumentWithNothingToTakeIsNotWrittenAgain(): void
    {
        $html = '<IMG SRC="absent.png"><style>a{}</style>';

        self::assertSame($html, new LocalAssets($this->assets)->inline($html));
    }

    public function testANulByteInTheDocumentIsReplaced(): void
    {
        self::assertStringContainsString("a\u{FFFD}b", $this->inline("<img src=\"logo.png\">a\0b"));
    }

    public function testTheDocumentKeepsItsAccents(): void
    {
        self::assertStringContainsString('Été', new LocalAssets($this->assets)->inline('<meta charset="latin1"><img src="logo.png">Été'));
    }

    public function testAMissingDirectoryIsAFailure(): void
    {
        $this->expectException(WeasyPrintFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The assets directory "%s/absent" does not exist.', $this->base));

        new LocalAssets($this->base.'/absent')->inline('<p>x</p>');
    }

    public function testAFileInsteadOfADirectoryIsAFailure(): void
    {
        $this->expectException(WeasyPrintFailed::class);

        new LocalAssets($this->assets.'/logo.png')->inline('<p>x</p>');
    }

    public function testAnEmptyFileIsTaken(): void
    {
        file_put_contents($this->assets.'/empty.png', '');

        self::assertStringContainsString('src="data:image/png;base64,"', $this->inline('<img src="empty.png">'));
    }

    public function testAFileAtTheLimitIsTaken(): void
    {
        self::assertStringContainsString('base64,bG9nbw==', new LocalAssets($this->assets, 4)->inline('<img src="logo.png">'));
    }

    public function testAFileOverTheLimitIsRefused(): void
    {
        $this->expectException(WeasyPrintFailed::class);
        $this->expectExceptionMessageIsOrContains('The asset "logo.png" is over the limit of 3 bytes.');

        new LocalAssets($this->assets, 3)->inline('<img src="logo.png">');
    }

    public function testTheTotalLimitCountsEveryUse(): void
    {
        self::assertSame(2, substr_count(new LocalAssets($this->assets, 4, 8)->inline('<img src="logo.png"><img src="logo.png">'), 'base64,'));

        $this->expectException(WeasyPrintFailed::class);
        $this->expectExceptionMessageIsOrContains('The assets of the document are over the total limit.');

        new LocalAssets($this->assets, 4, 7)->inline('<img src="logo.png"><img src="logo.png">');
    }

    public function testTheLimitsMustBePositive(): void
    {
        new LocalAssets($this->assets, 1, 1);

        $this->expectException(InvalidArgumentException::class);

        new LocalAssets($this->assets, 0, 1);
    }

    public function testTheTotalLimitMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LocalAssets($this->assets, 1, 0);
    }

    private function inline(string $body): string
    {
        return new LocalAssets($this->assets)->inline('<!doctype html><html><head><meta charset="utf-8"></head><body>'.$body.'</body></html>');
    }
}
