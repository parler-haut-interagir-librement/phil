<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Domain;

use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Policy::class)]
final class PolicyTest extends TestCase
{
    public function testTheHeaderKeepsTheOrderOfTheDirectives(): void
    {
        $policy = new Policy([
            'default-src' => ["'self'"],
            'img-src' => ["'self'", 'data:', "'self'"],
            'upgrade-insecure-requests' => true,
            'sandbox' => [],
            'frame-src' => false,
            'trusted-types' => ['app'],
        ]);

        self::assertSame("default-src 'self'; img-src 'self' data:; upgrade-insecure-requests; sandbox; trusted-types app", $policy->header());
        self::assertSame(['default-src' => ["'self'"], 'img-src' => ["'self'", 'data:'], 'upgrade-insecure-requests' => [], 'sandbox' => [], 'trusted-types' => ['app']], $policy->directives());
        self::assertSame(["'self'", 'data:'], $policy->sources(Directive::ImgSrc));
        self::assertNull($policy->sources(Directive::FrameSrc));
    }

    public function testASourceListWithoutSourceIsNone(): void
    {
        self::assertSame("object-src 'none'; require-trusted-types-for 'script'", new Policy(['object-src' => [], 'require-trusted-types-for' => true])->header());
    }

    public function testAnEmptyPolicyHasNoHeader(): void
    {
        self::assertSame('', new Policy()->header([], 'report-to csp'));
        self::assertSame('', new Policy()->header(['script-src' => ["'nonce-a'"]], 'report-to csp'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an unknown directive' => [['script' => ["'self'"]], '"script" is not a directive of a content security policy.'];
        yield 'a directive keyed by a number' => [[0 => ["'self'"]], '"0" is not a directive of a content security policy.'];
        yield 'a reporting directive' => [['report-uri' => ['/r']], '"report-uri" is not a directive of a content security policy.'];
        yield 'a value that is not a string' => [['img-src' => [1]], 'A value of "img-src" is not a string.'];
        yield 'none with another source' => [['img-src' => ["'self'", "'none'"]], "'none' stands alone in \"img-src\"."];
        yield 'an invalid source' => [['img-src' => ['self']], '"self" is not a value of "img-src"'];
    }

    /**
     * @param array<array-key, mixed> $directives
     */
    #[DataProvider('invalid')]
    public function testAnInvalidPolicyIsRefused(array $directives, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Policy($directives); // @phpstan-ignore argument.type
    }

    public function testNoneInTrustedTypesStandsWithOthers(): void
    {
        self::assertSame("trusted-types 'none' 'allow-duplicates'", new Policy(['trusted-types' => ["'none'", "'allow-duplicates'"]])->header());
    }

    public function testAddingSourcesReplacesNone(): void
    {
        $policy = new Policy(['default-src' => ["'self'"], 'img-src' => ["'none'"]])->withAdded('img-src', ['https://cdn.example.com', 'data:']);

        self::assertSame(['https://cdn.example.com', 'data:'], $policy->sources(Directive::ImgSrc));
    }

    public function testAddingSourcesKeepsThoseThere(): void
    {
        $policy = new Policy(['img-src' => ["'self'"]])->withAdded(Directive::ImgSrc, ['data:', "'self'"]);

        self::assertSame(["'self'", 'data:'], $policy->sources(Directive::ImgSrc));
    }

    public function testAnAddedFetchDirectiveStartsFromDefaultSrc(): void
    {
        $policy = new Policy(['default-src' => ["'self'"]]);

        self::assertSame(["'self'", 'https://cdn.example.com'], $policy->withAdded('img-src', ['https://cdn.example.com'])->sources(Directive::ImgSrc));
        self::assertSame(['https://pay.example.com'], $policy->withAdded('form-action', ['https://pay.example.com'])->sources(Directive::FormAction));
        self::assertSame(['https://cdn.example.com'], new Policy(['default-src' => ["'none'"]])->withAdded('img-src', ['https://cdn.example.com'])->sources(Directive::ImgSrc));
        self::assertSame(['https://cdn.example.com'], new Policy()->withAdded('img-src', ['https://cdn.example.com'])->sources(Directive::ImgSrc));
    }

    public function testAddingAFlag(): void
    {
        self::assertSame('upgrade-insecure-requests', new Policy()->withAdded('upgrade-insecure-requests', [])->header());
    }

    public function testAnAddedSourceIsChecked(): void
    {
        $this->expectException(InvalidPolicy::class);

        new Policy()->withAdded('img-src', ['bad source']);
    }

    public function testReplacingKeepsThePlaceOfTheDirective(): void
    {
        $policy = new Policy(['default-src' => ["'self'"], 'img-src' => ["'self'"], 'font-src' => ["'self'"]]);

        self::assertSame("default-src 'self'; img-src data:; font-src 'self'; script-src 'none'", $policy->withReplaced('img-src', ['data:'])->withReplaced(Directive::ScriptSrc, [])->header());
    }

    public function testRemovingADirective(): void
    {
        $policy = new Policy(['default-src' => ["'self'"], 'img-src' => ["'self'"]]);

        self::assertSame("default-src 'self'", $policy->without('img-src')->header());
        self::assertSame("img-src 'self'", $policy->without(Directive::DefaultSrc)->header());
        self::assertSame("default-src 'self'; img-src 'self'", $policy->without('frame-src')->header());
    }

    public function testRemovingAnUnknownDirectiveIsRefused(): void
    {
        $this->expectException(InvalidPolicy::class);

        new Policy()->without('script');
    }

    public function testAChildReplacesTheDirectivesItDeclares(): void
    {
        $parent = new Policy(['default-src' => ["'self'"], 'img-src' => ["'self'"]]);
        $child = new Policy(['img-src' => ['data:'], 'sandbox' => true]);

        self::assertSame("default-src 'self'; img-src data:; sandbox", $parent->extendedBy($child)->header());
    }

    public function testTokensGoAfterTheSourcesOfTheirDirective(): void
    {
        $policy = new Policy(['default-src' => ["'self'"], 'script-src' => ["'self'", "'nonce-a'"], 'object-src' => ["'none'"]]);

        self::assertSame(
            "default-src 'self'; script-src 'self' 'nonce-a' 'nonce-b'; object-src 'none'; style-src 'self' 'nonce-c'; report-uri /r",
            $policy->header(['script-src' => ["'nonce-a'", "'nonce-b'"], 'style-src' => ["'nonce-c'"], 'object-src' => ["'nonce-d'"], 'form-action' => ["'nonce-e'"]], 'report-uri /r'),
        );
    }

    public function testTokensFollowEverySourceOfDefaultSrc(): void
    {
        $policy = new Policy(['default-src' => ["'self'", 'https://a.example']]);

        self::assertSame("default-src 'self' https://a.example; style-src 'self' https://a.example 'nonce-c'", $policy->header(['style-src' => ["'nonce-c'"]]));
    }

    public function testTokensLeaveAnUnrestrictedDirectiveAlone(): void
    {
        $policy = new Policy(['img-src' => ["'self'"]]);

        self::assertSame("img-src 'self'", $policy->header(['script-src' => ["'nonce-a'"]]));
        self::assertSame("img-src 'self'; report-to csp", $policy->header([], 'report-to csp'));
    }

    public function testTokensLeaveNoneAlone(): void
    {
        $policy = new Policy(['default-src' => ["'none'"]]);

        self::assertSame("default-src 'none'", $policy->header(['script-src' => ["'nonce-a'"]]));
    }
}
