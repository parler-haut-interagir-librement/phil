<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit\Auth;

use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\Auth\TrustEveryReport;
use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\FeedbackType;
use Phil\BounceParser\Mime\Headers;
use Phil\BounceParser\Mime\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticationResults::class)]
#[CoversClass(TrustEveryReport::class)]
final class AuthenticationResultsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function fields(): iterable
    {
        yield 'own server, trusted domain' => [['mx.example.net; dkim=pass header.d=fbl.example.com'], true];
        yield 'case and version' => [['MX.Example.NET 1; DKIM = Pass HEADER.D="FBL.example.com"'], true];
        yield 'spaces before the id' => [["  \tmx.example.net; dkim=pass header.d=fbl.example.com"], true];
        yield 'subdomain' => [['mx.example.net; dkim=pass header.d=a.b.fbl.example.com'], true];
        yield 'parent domain' => [['mx.example.net; dkim=pass header.d=example.com'], false];
        yield 'look-alike domain' => [['mx.example.net; dkim=pass header.d=evilfbl.example.com'], false];
        yield 'other server' => [['other.example; dkim=pass header.d=fbl.example.com'], false];
        yield 'failed' => [['mx.example.net; dkim=fail header.d=fbl.example.com'], false];
        yield 'passed without domain' => [['mx.example.net; dkim=pass'], false];
        yield 'passed with spf only' => [['mx.example.net; spf=pass smtp.mailfrom=fbl.example.com'], false];
        yield 'dkimx' => [['mx.example.net; dkimx=pass header.d=fbl.example.com'], false];
        yield 'passed later' => [['mx.example.net; spf=fail; dkim=pass header.d=fbl.example.com'], true];
        yield 'second field' => [['other.example; dkim=pass header.d=fbl.example.com', 'mx.example.net; dkim=pass header.d=fbl.example.com'], true];
        yield 'semicolon in a comment' => [['mx.example.net; dkim=pass (good; key) header.d=fbl.example.com'], true];
        yield 'none' => [[], false];
        yield 'empty' => [[''], false];
        yield 'id alone' => [['mx.example.net'], false];
        yield 'pass in the id segment' => [['mx.example.net dkim=pass header.d=fbl.example.com'], false];
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('fields')]
    public function testVouchesForAPassOfItsOwnServer(array $values, bool $expected): void
    {
        $message = new Part(new Headers(array_map(static fn (string $value): array => ['Authentication-Results', $value], $values)), 'multipart/report', [], '');
        $authenticator = new AuthenticationResults(['Mx.Example.Net', 'mx2.example.net'], ['FBL.example.com', 'fbl.example.org']);

        self::assertSame($expected, $authenticator->isAuthentic($message, new FeedbackReport(FeedbackType::Abuse)));
        self::assertTrue(new TrustEveryReport()->isAuthentic($message, new FeedbackReport(FeedbackType::Abuse)));
    }

    public function testTrustsEachDomainGiven(): void
    {
        $message = new Part(new Headers([['Authentication-Results', 'mx2.example.net; dkim=pass header.d=fbl.example.org']]), 'text/plain', [], '');

        self::assertTrue(new AuthenticationResults(['mx.example.net', 'mx2.example.net'], ['fbl.example.com', 'fbl.example.org'])->isAuthentic($message, new FeedbackReport(FeedbackType::Abuse)));
    }
}
