<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\Rule\Canonical;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Canonical::class)]
final class CanonicalTest extends RuleTestCase
{
    private const string PUBLISHED = 'https://example.org/fr/news/opening';

    public function testTheRuleHasItsName(): void
    {
        self::assertSame('canonical', new Canonical()->name());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('canonicals')]
    public function testThePageNamesTheAddressItWasPublishedAt(string $head, array $expected): void
    {
        self::assertSame($expected, self::lines(new Canonical(), Pages::checked(Pages::html($head))));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function canonicals(): iterable
    {
        $path = ' @ /fr/news/opening';

        yield 'the same' => ['<link rel="canonical" href="https://example.org/fr/news/opening">', []];
        yield 'the same, written otherwise' => ['<link rel="Canonical" href=" HTTPS://EXAMPLE.org:443/fr/news/../news/opening ">', []];
        yield 'among other relations' => ['<link rel="nofollow canonical" href="https://example.org/fr/news/opening">', []];
        yield 'missing' => ['<link rel="alternate" href="https://example.org/fr/news/opening">', ['error canonical.missing (expected='.self::PUBLISHED.')'.$path]];
        yield 'without href' => ['<link rel="canonical">', ['error canonical.missing (expected='.self::PUBLISHED.')'.$path]];
        yield 'relative' => ['<link rel="canonical" href="/fr/news/opening">', ['warning canonical.relative (href=/fr/news/opening)'.$path]];
        yield 'relative and elsewhere' => ['<link rel="canonical" href="other">', [
            'warning canonical.relative (href=other)'.$path,
            'error canonical.mismatch (found=https://example.org/fr/news/other, expected='.self::PUBLISHED.')'.$path,
        ]];
        yield 'another address' => ['<link rel="canonical" href="https://example.org/fr/news/opening?page=2">', ['error canonical.mismatch (found=https://example.org/fr/news/opening?page=2, expected='.self::PUBLISHED.')'.$path]];
        yield 'not an address' => ['<link rel="canonical" href="mailto:shop@example.org">', ['error canonical.invalid (href=mailto:shop@example.org)'.$path]];
        yield 'two alike' => ['<link rel="canonical" href="https://example.org/fr/news/opening"><link rel="canonical" href="/fr/news/opening">', ['warning canonical.relative (href=/fr/news/opening)'.$path]];
        yield 'two different' => ['<link rel="canonical" href="https://example.org/fr/news/opening"><link rel="canonical" href="https://example.org/fr/">', ['error canonical.conflicting (count=2, path=/fr/news/opening)'.$path]];
        yield 'one invalid among two' => ['<link rel="canonical" href="https://example.org/fr/news/opening"><link rel="canonical" href="javascript:void(0)">', ['error canonical.invalid (href=javascript:void(0))'.$path]];
    }

    public function testEveryPathToThePageNamesTheSameCanonicalAddress(): void
    {
        $home = Pages::html('<link rel="canonical" href="https://example.org/fr/news/opening">');
        $page = Pages::checked($home, others: [
            Pages::rendered($home, 'https://example.org/'),
            Pages::rendered(Pages::html('<link rel="canonical" href="https://example.org/">'), 'https://example.org/fr/?ref=menu'),
            Pages::rendered(Pages::html(''), 'https://example.org/accueil'),
        ]);

        self::assertSame([
            'error canonical.variant-mismatch (path=/fr/?ref=menu, found=https://example.org/, expected='.self::PUBLISHED.') @ /fr/?ref=menu',
            'error canonical.variant-missing (path=/accueil, expected='.self::PUBLISHED.') @ /accueil',
        ], self::lines(new Canonical(), $page));
    }

    public function testARenderingThatDoesNotAnswerWithASuccessIsLeftOut(): void
    {
        $page = Pages::checked('', others: [
            Pages::rendered('', 'https://example.org/fr/', 301),
            Pages::rendered('', 'https://example.org/old', 404),
            Pages::rendered(Pages::html(''), 'https://example.org/fr/news/opening?amp=1', 299),
            Pages::rendered(Pages::html(''), 'https://example.org/fr/news/opening?print=1', 200),
        ], primary: Pages::rendered(Pages::html(''), status: 500));

        self::assertSame([
            'error canonical.variant-missing (path=/fr/news/opening?amp=1, expected='.self::PUBLISHED.') @ /fr/news/opening?amp=1',
            'error canonical.variant-missing (path=/fr/news/opening?print=1, expected='.self::PUBLISHED.') @ /fr/news/opening?print=1',
        ], self::lines(new Canonical(), $page));
    }

    public function testThePageAtItsAddressAnsweringWithASuccessIsChecked(): void
    {
        $page = Pages::checked('', primary: Pages::rendered(Pages::html(''), status: 204));

        self::assertSame(['error canonical.missing (expected='.self::PUBLISHED.') @ /fr/news/opening'], self::lines(new Canonical(), $page));
    }

    public function testARedirectionOf300IsLeftOut(): void
    {
        self::assertSame([], self::lines(new Canonical(), Pages::checked('', primary: Pages::rendered(Pages::html(''), status: 300))));
    }

    public function testAnInvalidCanonicalAddressDoesNotHideTheNextOne(): void
    {
        $head = '<link rel="canonical" href="https://[bad"><link rel="canonical" href="https://example.org/other">';

        self::assertSame([
            'warning canonical.relative (href=https://[bad) @ /fr/news/opening',
            'error canonical.invalid (href=https://[bad) @ /fr/news/opening',
            'error canonical.mismatch (found=https://example.org/other, expected='.self::PUBLISHED.') @ /fr/news/opening',
        ], self::lines(new Canonical(), Pages::checked(Pages::html($head))));
    }
}
