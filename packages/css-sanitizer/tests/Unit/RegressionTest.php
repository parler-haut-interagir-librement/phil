<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit;

use Phil\CssSanitizer\Check\Sanitization;
use Phil\CssSanitizer\Check\Values;
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Sanitized;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Url\AllowedFiles;
use Phil\CssSanitizer\Url\CallbackRewriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Regressions of the bypasses known to style sanitizers: addresses hidden by references or escapes, image sets that
 * mix a file with a script, a rewriter that fails, and an output that stays as it is when it is sanitized again.
 */
#[CoversClass(CssSanitizer::class)]
#[CoversClass(Sanitization::class)]
#[CoversClass(Values::class)]
#[CoversClass(Sanitized::class)]
#[CoversClass(Serializer::class)]
#[CoversClass(AllowedFiles::class)]
#[CoversClass(CallbackRewriter::class)]
final class RegressionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function hiddenAddresses(): iterable
    {
        yield 'a decimal reference padded with zeros' => ['a{background:url("&#0000106;avascript:alert(1)")}'];
        yield 'a CSS escape' => ['a{background:url(\6a avascript:alert(1))}'];
        yield 'a CSS escape padded with zeros' => ['a{background:url("\00006a avascript:alert(1)")}'];
        yield 'a NUL escape' => ['a{background:url(java\0script:alert(1))}'];
        yield 'a special scheme without its authority' => ['a{background:url("https:evil.example/a.png")}'];
        yield 'an address without its scheme' => ['a{background:url("//evil.example/a.png")}'];
        yield 'an image set that mixes a file with a script' => ['a{background-image:image-set("a.png" 1x, "javascript:alert(1)" 2x)}'];
        yield 'a list that ends with a reference' => ['a{background:url(a.png), url(&#0000106;)}'];
        yield 'a fragment after the name' => ['a{background:url("a.png#x")}'];
        yield 'a space before the name' => ['a{background:url(" a.png")}'];
    }

    /**
     * Only the names the rewriter knows are written, and each as the address it maps to: a reference, an escape or a
     * scheme never smuggles another address through.
     */
    #[DataProvider('hiddenAddresses')]
    public function testOnlyTheFilesTheRewriterKnowsAreWritten(string $css): void
    {
        $sanitized = $this->sanitizer()->sanitize($css);

        self::assertSame('', $sanitized->css);
        self::assertFalse($sanitized->isClean());
    }

    /**
     * An escaped name is the name the browser reads: `\61 .png` is `a.png`, and `u\rl(` is `url(`.
     */
    public function testAnEscapedNameIsReadAsTheBrowserReadsIt(): void
    {
        foreach (['a{background:url(\61 .png)}', 'a{background:u\rl(a.png)}', 'a{background:url(./a.png)}'] as $css) {
            self::assertSame("a {\n  background: url(\"/img/a.png\");\n}\n", $this->sanitizer()->sanitize($css)->css, $css);
        }
    }

    /**
     * A rewriter that throws stops the sanitization: its exception comes through, and no partial sheet is returned.
     */
    public function testARewriterThatThrowsStopsTheSanitization(): void
    {
        $sanitizer = new CssSanitizer(new Policy()->withUrlRewriter(new CallbackRewriter(static fn (): never => throw new RuntimeException('The file store is down.'))));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The file store is down.');

        $sanitizer->sanitize('a{color:red;background:url(a.png)}');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sheets(): iterable
    {
        yield 'a closing style tag' => ['a{color:red;}</style><script>alert(1)</script>'];
        yield 'a closing style tag in a string' => ['a{content:"</style><script>"}'];
        yield 'an unclosed block' => ['a{color:red'];
        yield 'an unclosed string' => ['a{content:"abc'];
        yield 'comments inside a name' => ['a{col/**/or:red}'];
        yield 'escapes in a selector' => ['.\31 a, #\@x{color:red}'];
        yield 'nested rules' => ['@media (min-width: 10px){a{color:red}@supports (display:grid){b{display:grid}}}'];
        yield 'an expression' => ['a{width:expression(alert(1));color:blue}'];
        yield 'a NUL byte' => ["a{color:r\0ed}"];
        yield 'a mapped file' => ['a{background:url(a.png) no-repeat}'];
    }

    /**
     * Sanitizing an output again changes nothing and removes nothing, so that a sheet stored sanitized and sanitized
     * again on reading reads the same.
     */
    #[DataProvider('sheets')]
    public function testAnOutputSanitizedAgainIsUnchanged(string $css): void
    {
        $sanitizer = new CssSanitizer(new Policy()->withUrlRewriter(new AllowedFiles(['a.png' => 'a.png'])));
        $once = $sanitizer->sanitize($css);
        $twice = $sanitizer->sanitize($once->css);

        self::assertSame($once->css, $twice->css);
        self::assertTrue($twice->isClean());
    }

    private function sanitizer(): CssSanitizer
    {
        return new CssSanitizer(new Policy()->withUrlRewriter(new AllowedFiles(['a.png' => '/img/a.png'])));
    }
}
