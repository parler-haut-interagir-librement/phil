<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\Rule\BrokenLinks;
use Phil\SeoCheck\Tests\Support\MemoryLinkStatuses;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BrokenLinks::class)]
final class BrokenLinksTest extends RuleTestCase
{
    private const string BODY = '<a href="/fr/horaires#samedi">Horaires</a>'
        .'<a href="https://example.org/fr/horaires">Les horaires</a>'
        .'<a href="https://partner.example.com/gone">Partenaire</a>'
        .'<a href="https://partner.example.com/busy">Occupé</a>'
        .'<a href="https://partner.example.com/moved">Déplacé</a>'
        .'<a href="https://partner.example.com/unknown">Inconnu</a>'
        .'<a href="mailto:shop@example.org">Courriel</a><a href="javascript:go()">Script</a><a>Rien</a>';

    public function testTheRuleHasItsName(): void
    {
        self::assertSame('broken-links', new BrokenLinks(new MemoryLinkStatuses())->name());
    }

    public function testDeadLinksAreReportedOnceEach(): void
    {
        $statuses = new MemoryLinkStatuses([
            'https://example.org/fr/horaires' => 200,
            'https://partner.example.com/gone' => 404,
            'https://partner.example.com/busy' => 503,
            'https://partner.example.com/moved' => 301,
            'https://example.org/elsewhere' => 410,
        ]);

        self::assertSame([
            'error broken-links.broken (href=https://partner.example.com/gone, status=404) @ https://partner.example.com/gone',
            'warning broken-links.server-error (href=https://partner.example.com/busy, status=503) @ https://partner.example.com/busy',
            'info broken-links.redirect (href=https://partner.example.com/moved, status=301) @ https://partner.example.com/moved',
        ], self::lines(new BrokenLinks($statuses), Pages::checked(Pages::html(body: self::BODY))));
        self::assertSame([[
            'https://example.org/fr/horaires',
            'https://partner.example.com/gone',
            'https://partner.example.com/busy',
            'https://partner.example.com/moved',
            'https://partner.example.com/unknown',
        ]], $statuses->asked);
    }

    public function testTheBoundsOfEachStatus(): void
    {
        $body = '<a href="/a">A</a><a href="/b">B</a><a href="/c">C</a><a href="/d">D</a><a href="/e">E</a>';
        $statuses = new MemoryLinkStatuses([
            'https://example.org/a' => 299,
            'https://example.org/b' => 300,
            'https://example.org/c' => 399,
            'https://example.org/d' => 499,
            'https://example.org/e' => 500,
        ]);

        self::assertSame([
            'info broken-links.redirect (href=https://example.org/b, status=300) @ https://example.org/b',
            'info broken-links.redirect (href=https://example.org/c, status=399) @ https://example.org/c',
            'error broken-links.broken (href=https://example.org/d, status=499) @ https://example.org/d',
            'warning broken-links.server-error (href=https://example.org/e, status=500) @ https://example.org/e',
        ], self::lines(new BrokenLinks($statuses), Pages::checked(Pages::html(body: $body))));
    }

    public function testAtMostSoManyLinksAreAskedAbout(): void
    {
        $statuses = new MemoryLinkStatuses();

        self::lines(new BrokenLinks($statuses, 2), Pages::checked(Pages::html(body: '<a href="/a">A</a><a href="/a#b">A</a><a href="/b">B</a><a href="/c">C</a>')));

        self::assertSame([['https://example.org/a', 'https://example.org/b']], $statuses->asked);
    }

    public function testAPageWithoutLinksAsksNothing(): void
    {
        $statuses = new MemoryLinkStatuses();

        self::assertSame([], self::lines(new BrokenLinks($statuses), Pages::checked(Pages::html(body: '<a href="mailto:a@example.org">A</a>'))));
        self::assertSame([], $statuses->asked);
    }

    public function testAStatusOf400IsBroken(): void
    {
        $page = Pages::checked(Pages::html(body: '<h1>T</h1><a href="https://example.org/bad">Bad</a>'));

        self::assertSame(['error broken-links.broken (href=https://example.org/bad, status=400) @ https://example.org/bad'], self::lines(new BrokenLinks(new MemoryLinkStatuses(['https://example.org/bad' => 400])), $page));
    }
}
