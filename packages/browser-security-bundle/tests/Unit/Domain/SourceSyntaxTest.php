<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Domain;

use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceSyntax::class)]
final class SourceSyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{Directive, string}>
     */
    public static function valid(): iterable
    {
        foreach (SourceSyntax::KEYWORDS as $keyword) {
            yield 'script-src '.$keyword => [Directive::ScriptSrc, $keyword];
        }

        yield 'a nonce' => [Directive::ScriptSrc, "'nonce-ab+/_-c=='"];
        yield 'a sha256' => [Directive::StyleSrc, "'sha256-abc='"];
        yield 'a sha384' => [Directive::DefaultSrc, "'sha384-abc'"];
        yield 'a sha512' => [Directive::ScriptSrcElem, "'sha512-abc'"];
        yield 'a scheme' => [Directive::ImgSrc, 'data:'];
        yield 'a scheme with symbols' => [Directive::ConnectSrc, 'web+app.x-1:'];
        yield 'the wildcard' => [Directive::ImgSrc, '*'];
        yield 'a host' => [Directive::ImgSrc, 'cdn.example.com'];
        yield 'a trailing dot' => [Directive::ImgSrc, 'cdn.example.com.'];
        yield 'a host wildcard' => [Directive::ImgSrc, '*.example.com'];
        yield 'a full source' => [Directive::ConnectSrc, 'wss://api.example.com:8443/socket/path_~!$&()*+=:@%-'];
        yield 'a wildcard port' => [Directive::ConnectSrc, 'https://example.com:*'];
        yield 'self in frame-ancestors' => [Directive::FrameAncestors, "'self'"];
        yield 'none in base-uri' => [Directive::BaseUri, "'none'"];
        yield 'a host in form-action' => [Directive::FormAction, 'https://pay.example.com'];

        foreach (SourceSyntax::SANDBOX_FLAGS as $flag) {
            yield 'sandbox '.$flag => [Directive::Sandbox, $flag];
        }

        yield 'trusted types none' => [Directive::TrustedTypes, "'none'"];
        yield 'trusted types duplicates' => [Directive::TrustedTypes, "'allow-duplicates'"];
        yield 'trusted types any' => [Directive::TrustedTypes, '*'];
        yield 'a trusted types policy' => [Directive::TrustedTypes, 'dompurify#1=_/@.%-x'];
        yield 'require trusted types' => [Directive::RequireTrustedTypesFor, "'script'"];
        yield 'the longest value' => [Directive::ImgSrc, str_repeat('a', SourceSyntax::MAX_LENGTH)];
    }

    #[DataProvider('valid')]
    public function testAValueItsDirectiveTakes(Directive $directive, string $value): void
    {
        $this->expectNotToPerformAssertions();

        SourceSyntax::assertValid($directive, $value);
    }

    /**
     * @return iterable<string, array{Directive, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [Directive::ImgSrc, '', 'A value of "img-src" is empty or longer than 1024 bytes.'];
        yield 'too long' => [Directive::ImgSrc, str_repeat('a', SourceSyntax::MAX_LENGTH + 1), 'A value of "img-src" is empty or longer than 1024 bytes.'];
        yield 'unquoted self' => [Directive::ScriptSrc, 'self', '"self" is not a value of "script-src", a keyword is written between single quotes.'];
        yield 'unquoted upper case' => [Directive::ScriptSrc, 'NONE', '"NONE" is not a value of "script-src", a keyword is written between single quotes.'];
        yield 'unquoted nonce' => [Directive::ScriptSrc, 'nonce-abc', '"nonce-abc" is not a value of "script-src", a keyword is written between single quotes.'];
        yield 'unquoted hash' => [Directive::ScriptSrc, 'SHA256-abc', '"SHA256-abc" is not a value of "script-src", a keyword is written between single quotes.'];
        yield 'a semicolon' => [Directive::ScriptSrc, "'self'; script-src *", '"\'self\'; script-src *" is not a value of "script-src".'];
        yield 'a space' => [Directive::ImgSrc, 'a.example b.example', '"a.example b.example" is not a value of "img-src".'];
        yield 'a comma' => [Directive::ImgSrc, 'a.example,b.example', '"a.example,b.example" is not a value of "img-src".'];
        yield 'a quote in a path' => [Directive::ImgSrc, "example.com/a'b", '"example.com/a\'b" is not a value of "img-src".'];
        yield 'a semicolon in a path' => [Directive::ImgSrc, 'example.com/a;b', '"example.com/a;b" is not a value of "img-src".'];
        yield 'an unknown keyword' => [Directive::ScriptSrc, "'unsafe-everything'", '"\'unsafe-everything\'" is not a value of "script-src".'];
        yield 'a bad nonce' => [Directive::ScriptSrc, "'nonce-a b'", '"\'nonce-a b\'" is not a value of "script-src".'];
        yield 'too much padding' => [Directive::ScriptSrc, "'nonce-ab==='", '"\'nonce-ab===\'" is not a value of "script-src".'];
        yield 'an empty nonce' => [Directive::ScriptSrc, "'nonce-'", '"\'nonce-\'" is not a value of "script-src".'];
        yield 'a port too long' => [Directive::ImgSrc, 'example.com:123456', '"example.com:123456" is not a value of "img-src".'];
        yield 'a scheme alone with digits first' => [Directive::ImgSrc, '1http:', '"1http:" is not a value of "img-src".'];
        yield 'a wildcard inside a host' => [Directive::ImgSrc, 'cdn.*.example.com', '"cdn.*.example.com" is not a value of "img-src".'];
        yield 'a path without slash' => [Directive::ImgSrc, 'example.com:80x', '"example.com:80x" is not a value of "img-src".'];
        yield 'a host then text' => [Directive::ImgSrc, "example.com\nx", "\"example.com\nx\" is not a value of \"img-src\"."];
        yield 'a host then a new line' => [Directive::ImgSrc, "example.com\n", "\"example.com\n\" is not a value of \"img-src\"."];
        yield 'a scheme then a new line' => [Directive::ImgSrc, "data:\n", "\"data:\n\" is not a value of \"img-src\"."];
        yield 'a nonce then a new line' => [Directive::ScriptSrc, "'nonce-a'\n", "\"'nonce-a'\n\" is not a value of \"script-src\"."];
        yield 'a policy name then a new line' => [Directive::TrustedTypes, "a\n", "\"a\n\" is not a value of \"trusted-types\"."];
        yield 'text then a host' => [Directive::ImgSrc, "x\nexample.com", "\"x\nexample.com\" is not a value of \"img-src\"."];
        yield 'a scheme then text' => [Directive::ImgSrc, "data:\nx", "\"data:\nx\" is not a value of \"img-src\"."];
        yield 'text then a scheme' => [Directive::ImgSrc, "x\ndata:", "\"x\ndata:\" is not a value of \"img-src\"."];
        yield 'a nonce then text' => [Directive::ScriptSrc, "'nonce-a'\nx", "\"'nonce-a'\nx\" is not a value of \"script-src\"."];
        yield 'text then a nonce' => [Directive::ScriptSrc, "x\n'nonce-a'", "\"x\n'nonce-a'\" is not a value of \"script-src\"."];
        yield 'unsafe-inline in frame-ancestors' => [Directive::FrameAncestors, "'unsafe-inline'", '"\'unsafe-inline\'" is not a value of "frame-ancestors".'];
        yield 'strict-dynamic in base-uri' => [Directive::BaseUri, "'strict-dynamic'", '"\'strict-dynamic\'" is not a value of "base-uri".'];
        yield 'a nonce in form-action' => [Directive::FormAction, "'nonce-abc'", '"\'nonce-abc\'" is not a value of "form-action".'];
        yield 'a hash in frame-ancestors' => [Directive::FrameAncestors, "'sha256-abc'", '"\'sha256-abc\'" is not a value of "frame-ancestors".'];
        yield 'an unknown sandbox flag' => [Directive::Sandbox, 'allow-everything', '"allow-everything" is not a value of "sandbox".'];
        yield 'a source in sandbox' => [Directive::Sandbox, "'self'", '"\'self\'" is not a value of "sandbox".'];
        yield 'a value of upgrade' => [Directive::UpgradeInsecureRequests, 'yes', '"yes" is not a value of "upgrade-insecure-requests".'];
        yield 'require trusted types for style' => [Directive::RequireTrustedTypesFor, "'style'", '"\'style\'" is not a value of "require-trusted-types-for".'];
        yield 'a trusted types policy with a space' => [Directive::TrustedTypes, 'a b', '"a b" is not a value of "trusted-types".'];
        yield 'a trusted types policy then text' => [Directive::TrustedTypes, "a\n;", "\"a\n;\" is not a value of \"trusted-types\"."];
        yield 'text then a trusted types policy' => [Directive::TrustedTypes, ";\na", "\";\na\" is not a value of \"trusted-types\"."];
    }

    #[DataProvider('invalid')]
    public function testAValueItsDirectiveRefuses(Directive $directive, string $value, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        SourceSyntax::assertValid($directive, $value);
    }

    public function testNoncesAndHashesAreRecognised(): void
    {
        self::assertTrue(SourceSyntax::isNonceOrHash("'nonce-abc'"));
        self::assertTrue(SourceSyntax::isNonceOrHash("'sha384-abc='"));
        self::assertFalse(SourceSyntax::isNonceOrHash("'self'"));
        self::assertFalse(SourceSyntax::isNonceOrHash("'sha1-abc'"));
    }

    public function testTheHashOfAnInlineElement(): void
    {
        self::assertSame("'sha256-".sodium_bin2base64(hash('sha256', 'alert(1)', true), \SODIUM_BASE64_VARIANT_ORIGINAL)."'", SourceSyntax::hashOf('alert(1)'));
    }
}
