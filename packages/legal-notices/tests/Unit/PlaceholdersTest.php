<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\Placeholders;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\RetentionDuration;
use Phil\LegalNotices\RetentionKind;
use Phil\LegalNotices\SiteIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Placeholders::class)]
#[CoversClass(PlainTextBody::class)]
final class PlaceholdersTest extends TestCase
{
    public function testTheValuesAreFilledEscaped(): void
    {
        $placeholders = new Placeholders(['publisher_name' => '<b>"A" & \'B\'</b>', 'publisher_address' => "1 Main\nMontréal", 'retention.R-1' => '90 days', 'bad' => "\xC3"]);

        self::assertSame(
            "<p>&lt;b&gt;\"A\" &amp; 'B'&lt;/b&gt; at 1 Main<br>Montréal keeps it 90 days; \${unknown} stays, 50% too, \u{FFFD}</p>",
            $placeholders->fill('<p>${publisher_name} at ${publisher_address} keeps it ${retention.R-1}; ${unknown} stays, 50% too, ${bad}</p>'),
        );
        self::assertSame(['unknown', 'other.x'], $placeholders->unknown('${publisher_name} ${unknown} ${other.x} ${unknown}'));
    }

    public function testAnHtmlWithoutAValueToFillIsLeftAsWritten(): void
    {
        $placeholders = new Placeholders(['publisher_name' => 'Example']);

        self::assertSame('<p>&apos;a&apos; ${unknown}</p><td>', $placeholders->fill('<p>&apos;a&apos; ${unknown}</p><td>'));
        self::assertSame("<p>'a' Example</p>", $placeholders->fill('<p>&apos;a&apos; ${publisher_name}</p>'));
    }

    public function testAValueIsFilledIntoTheTextOnly(): void
    {
        $placeholders = new Placeholders(['site_name' => 'javascript:alert(1)', 'publisher_name' => '"><script>alert(1)</script>', 'host_name' => "</style><script>alert(1)</script>\nnext"]);
        $html = '<p><a href="${site_name}" title="${publisher_name}">${site_name}</a> <img src="${site_name}" alt="">'
            .'<!-- ${host_name} --><em>${host_name}</em></p><template><i>${host_name}</i></template>';

        self::assertSame(
            '<p><a href="${site_name}" title="${publisher_name}">javascript:alert(1)</a> <img src="${site_name}" alt="">'
            .'<!-- ${host_name} --><em>&lt;/style&gt;&lt;script&gt;alert(1)&lt;/script&gt;<br>next</em></p><template><i>${host_name}</i></template>',
            $placeholders->fill($html),
        );
        self::assertSame(['site_name', 'publisher_name', 'host_name'], $placeholders->misplaced($html));
        self::assertSame([], $placeholders->misplaced('<p>${site_name} ${nope}</p>'));
        self::assertSame([], $placeholders->misplaced('<p>${nope}</p>'));
        self::assertSame(['site_name'], $placeholders->misplaced('<p>${nope}</p><a href="${site_name}">a</a>'));
        self::assertSame([], new Placeholders(['site_name' => '${publisher_name}', 'publisher_name' => 'E'])->misplaced('<p>${site_name}</p>'));
    }

    public function testTheTextOfAnElementTheBrowserDoesNotReadAsHtmlIsLeftAsWritten(): void
    {
        $placeholders = new Placeholders(['host_name' => '</x><script>alert(1)</script>']);

        foreach (['iframe', 'noembed', 'noframes', 'noscript', 'plaintext', 'script', 'style', 'xmp'] as $element) {
            $html = \sprintf('<div><%1$s>${host_name}</%1$s></div>${host_name}', $element);

            self::assertStringNotContainsString('<script>alert', $placeholders->fill($html), $element);
            self::assertStringContainsString('${host_name}', $placeholders->fill($html), $element);
            self::assertSame(['host_name'], $placeholders->misplaced($html), $element);
        }

        self::assertSame('<div><span>a &lt;/x&gt;&lt;script&gt;alert(1)&lt;/script&gt;</span></div>', $placeholders->fill('<div><span>a ${host_name}</span></div>'));
    }

    public function testTheNamesOfAText(): void
    {
        self::assertSame(['a', 'retention.R-001', 'consent.analytics', 'b_2'], Placeholders::names('${a} ${retention.R-001} ${consent.analytics} ${a} ${b_2} ${A} ${1a} ${a.} ${a b} {a} $a ${a}b} [[c]] {{d}} 50% ${'.str_repeat('a', 65).'}'));
        self::assertSame([], Placeholders::names('nothing'));
    }

    public function testTheValuesOfAVersion(): void
    {
        $version = new LegalDocumentVersion(DocumentType::PrivacyPolicy, 3, ['en' => 'a'], 'text', new DateTimeImmutable('2026-12-01 10:00'));
        $placeholders = Placeholders::of(
            $version,
            new SiteIdentity(['publisher_name' => 'Example', 'dpo_contact' => 'dpo@example.com']),
            [new RetentionDuration('R-1', 'Accounts', RetentionKind::Fixed, 'P2Y')],
            [new ConsentCategory('analytics', false, ['en' => 'Audience', 'fr' => 'Audience (fr)'])],
            'fr',
            'en',
        );

        self::assertSame('Example, dpo@example.com, 3, 2026-12-01, 2 ans, Audience (fr)', $placeholders->fill('${publisher_name}, ${dpo_contact}, ${version}, ${effective_date}, ${retention.R-1}, ${consent.analytics}'));
        self::assertSame('Audience', Placeholders::of($version, new SiteIdentity(), [], [new ConsentCategory('analytics', false, ['en' => 'Audience', 'fr' => 'Audience (fr)'])], 'de', 'en')->fill('${consent.analytics}'));
    }

    public function testPlainTextIsEscapedInParagraphs(): void
    {
        $body = new PlainTextBody();

        self::assertSame("<p>First &amp; &lt;b&gt;line</p>\n<p>Second<br>\nthird</p>\n<p>Last</p>", $body->toHtml('text', "\n  First & <b>line  \r\n \r\n\r\n Second\rthird\n\n\n\nLast"));
        self::assertSame("<p>\u{FFFD}</p>", $body->toHtml('text', "\xC3"));
        self::assertSame('', $body->toHtml('text', " \n "));
        self::assertSame('<p>One</p>', $body->toHtml('text', 'One'));
        self::assertSame("<p>&quot;a&quot;<br>\n&apos;b&apos;</p>", $body->toHtml('text', "\"a\"\r\n'b'"));
    }

    public function testAnotherFormatIsRefused(): void
    {
        try {
            new PlainTextBody()->toHtml('markdown', 'a');
            self::fail('The format was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame(['unknown_format', 'The format "markdown" has no renderer.'], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }
}
