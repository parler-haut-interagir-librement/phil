<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Markup;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Infrastructure\Markup\RawHtmlRole;
use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use Phil\ContentBundle\Tests\Unit\Fixture\MarkingHtmlPurifier;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Every format a draft is written in, converted into the UJO a published row stores, and the HTML compiled from it.
 */
#[CoversClass(UjoBodyConverter::class)]
#[CoversClass(RawHtmlRole::class)]
#[CoversClass(ConversionLoss::class)]
final class UjoBodyConverterTest extends TestCase
{
    private const string HEADING_AND_PARAGRAPH = '{"time":1793462400123,"blocks":[{"id":"b1","type":"header","data":{"text":"Ouverture","level":2},"tunes":{"anchor":{"anchor":"ouverture"}}},{"id":"b2","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}';

    private const string HEADING_AND_PARAGRAPH_HTML = "<h2 id=\"ouverture\">Ouverture</h2>\n<p>Le site <b>ouvre</b>.</p>";

    #[DataProvider('formats')]
    public function testEveryFormatIsPublishedAsUjoAndItsHtml(MarkupFormat $format, string $source, string $ujo, string $html): void
    {
        $published = $this->converter()->toUjo(Body::of($source, $format, '<p>draft</p>'), $this->publishedAt());

        self::assertSame('ujo', $published->format);
        self::assertSame($ujo, $published->source);
        self::assertSame($html, $published->html);
    }

    /**
     * @return iterable<string, array{MarkupFormat, string, string, string}>
     */
    public static function formats(): iterable
    {
        yield 'Markdown' => [MarkupFormat::Markdown, "## Ouverture\n\nLe site **ouvre**.", self::HEADING_AND_PARAGRAPH, self::HEADING_AND_PARAGRAPH_HTML];
        yield 'BBCode' => [
            MarkupFormat::BBCode,
            "[h2]Ouverture[/h2]\n\nLe site [b]ouvre[/b].",
            '{"time":1793462400123,"blocks":[{"id":"b2","type":"header","data":{"text":"Ouverture","level":2},"tunes":{"anchor":{"anchor":"ouverture"}}},{"id":"b3","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}',
            self::HEADING_AND_PARAGRAPH_HTML,
        ];
        yield 'MediaWiki' => [MarkupFormat::MediaWiki, "== Ouverture ==\n\nLe site '''ouvre'''.", self::HEADING_AND_PARAGRAPH, self::HEADING_AND_PARAGRAPH_HTML];
        yield 'DokuWiki' => [MarkupFormat::DokuWiki, "===== Ouverture =====\n\nLe site **ouvre**.", self::HEADING_AND_PARAGRAPH, self::HEADING_AND_PARAGRAPH_HTML];
        yield 'Creole' => [MarkupFormat::Creole, "== Ouverture\n\nLe site **ouvre**.", self::HEADING_AND_PARAGRAPH, self::HEADING_AND_PARAGRAPH_HTML];
        yield 'HTML, converted into blocks' => [MarkupFormat::Html, '<h2>Ouverture</h2><p>Le site <strong>ouvre</strong>.</p>', self::HEADING_AND_PARAGRAPH, self::HEADING_AND_PARAGRAPH_HTML];
        yield 'plain text' => [
            MarkupFormat::Text,
            "Ouverture\n\nLe site ouvre.",
            '{"time":1793462400123,"blocks":[{"id":"b1","type":"paragraph","data":{"text":"Ouverture"}},{"id":"b2","type":"paragraph","data":{"text":"Le site ouvre."}}]}',
            "<p>Ouverture</p>\n<p>Le site ouvre.</p>",
        ];
        yield 'UJO, put in canonical form and dated again' => [
            MarkupFormat::Ujo,
            "{\n  \"time\": 1762400000000,\n  \"blocks\": [{\"id\": \"a\", \"type\": \"paragraph\", \"data\": {\"text\": \"Le site <b>ouvre</b>.\"}}]\n}",
            '{"time":1793462400123,"blocks":[{"id":"a","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}',
            '<p>Le site <b>ouvre</b>.</p>',
        ];
    }

    /**
     * Read at the level the draft was compiled at: raw HTML written in Markdown is text, as the draft showed it.
     */
    public function testRawHtmlInAWrittenFormatStaysText(): void
    {
        $published = $this->converter()->toUjo(Body::of("Le site **ouvre**.\n\n<script>alert(1)</script>", MarkupFormat::Markdown, ''), $this->publishedAt());

        self::assertSame('{"time":1793462400123,"blocks":[{"id":"b1","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}},{"id":"b2","type":"paragraph","data":{"text":"&lt;script&gt;alert(1)&lt;/script&gt;"}}]}', $published->source);
        self::assertSame("<p>Le site <b>ouvre</b>.</p>\n<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>", $published->html);
        self::assertSame(Level::Forum, UjoBodyConverter::LEVEL);
    }

    /**
     * A `raw` block written in UJO, at the top or nested, is a paragraph that shows its HTML, its tunes kept.
     */
    public function testARawBlockOfAUjoSourceBecomesText(): void
    {
        $source = '{"blocks":[{"id":"r","type":"raw","data":{"html":"<p>Bonjour</p>"},"tunes":{"anchor":{"anchor":"salut"}}},{"id":"n","type":"note","data":{"title":"","blocks":[{"id":"s","type":"raw","data":{"html":"<b>Ici</b>"}}]}}]}';
        $published = $this->converter()->toUjo(Body::of($source, MarkupFormat::Ujo, ''), $this->publishedAt());

        self::assertSame('{"time":1793462400123,"blocks":[{"id":"r","type":"paragraph","data":{"text":"&lt;p&gt;Bonjour&lt;/p&gt;"},"tunes":{"anchor":{"anchor":"salut"}}},{"id":"n","type":"note","data":{"title":"","blocks":[{"id":"s","type":"paragraph","data":{"text":"&lt;b&gt;Ici&lt;/b&gt;"}}]}}]}', $published->source);
        self::assertSame("<p id=\"salut\">&lt;p&gt;Bonjour&lt;/p&gt;</p>\n<aside><p>&lt;b&gt;Ici&lt;/b&gt;</p></aside>", $published->html);
    }

    /**
     * A publisher granted the trusted role publishes the raw HTML of a written format and the `raw` blocks of UJO,
     * kept as the `raw` profile allows.
     */
    public function testTheTrustedRolePublishesRawHtml(): void
    {
        $converter = $this->converter(new RawHtmlRole($this->authorization(['ROLE_EDITOR']), 'ROLE_EDITOR'));

        $markdown = $converter->toUjo(Body::of("Le site **ouvre**.\n\n<div><p>Bonjour</p><script>alert(1)</script></div>", MarkupFormat::Markdown, ''), $this->publishedAt());
        self::assertSame('{"time":1793462400123,"blocks":[{"id":"b1","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}},{"id":"b2","type":"raw","data":{"html":"<div><p>Bonjour</p></div>"}}]}', $markdown->source);
        self::assertSame("<p>Le site <b>ouvre</b>.</p>\n<div><div><p>Bonjour</p></div></div>", $markdown->html);

        $ujo = $converter->toUjo(Body::of('{"blocks":[{"id":"r","type":"raw","data":{"html":"<p>Bonjour</p>"}}]}', MarkupFormat::Ujo, ''), $this->publishedAt());
        self::assertSame('{"time":1793462400123,"blocks":[{"id":"r","type":"raw","data":{"html":"<p>Bonjour</p>"}}]}', $ujo->source);
    }

    public function testAPublisherWithoutTheTrustedRolePublishesRawHtmlAsText(): void
    {
        $published = $this->converter(new RawHtmlRole($this->authorization(['ROLE_USER']), 'ROLE_EDITOR'))
            ->toUjo(Body::of('<div>Bonjour</div>', MarkupFormat::Markdown, ''), $this->publishedAt());

        self::assertSame('{"time":1793462400123,"blocks":[{"id":"b1","type":"paragraph","data":{"text":"&lt;div&gt;Bonjour&lt;/div&gt;"}}]}', $published->source);
    }

    /**
     * The `time` is the publication moment in milliseconds, whatever its time zone; the editor's version stays.
     */
    public function testTheDocumentIsDatedWithThePublicationAndKeepsItsVersion(): void
    {
        $published = $this->converter()->toUjo(
            Body::of('{"time":5,"blocks":[],"version":"2.31.0"}', MarkupFormat::Ujo, ''),
            new DateTimeImmutable('2026-10-31 12:00:00.123', new DateTimeZone('America/Montreal')),
        );

        self::assertSame('{"time":1793462400123,"blocks":[],"version":"2.31.0"}', $published->source);
    }

    public function testTheHtmlIsPurifiedOnce(): void
    {
        $published = new UjoBodyConverter(new DefaultCompiler(), new MarkingHtmlPurifier())
            ->toUjo(Body::of('Bonjour', MarkupFormat::Markdown, ''), $this->publishedAt());

        self::assertSame('<purified><p class="ujo-paragraph">Bonjour</p></purified>', $published->html);
    }

    public function testWhatTheConversionLosesIsListed(): void
    {
        $losses = $this->converter()->of('<p>a<object>x</object><a href="ftp://x">f</a></p>', MarkupFormat::Html);

        self::assertEquals([new ConversionLoss('object', 'body > p[1] > object[1]', 'unknown-element'), new ConversionLoss('a', 'body > p[1] > a[1]', 'refused-scheme')], $losses);
        self::assertSame([], $this->converter()->of('*a*', MarkupFormat::Markdown));
        self::assertSame([], $this->converter(new RawHtmlRole($this->authorization(['ROLE_EDITOR']), 'ROLE_EDITOR'))->of('<p>a</p>', MarkupFormat::Html));
        $this->expectException(InvalidContent::class);
        $this->converter()->of('{"blocks": 1}', MarkupFormat::Ujo);
    }

    public function testAUjoSourceThatIsNotAValidDocumentIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The ujo source cannot be read: The block type "nope" at blocks[0] is not registered.');

        $this->converter()->toUjo(Body::of('{"blocks":[{"id":"a","type":"nope","data":{}}]}', MarkupFormat::Ujo, ''), $this->publishedAt());
    }

    private function converter(?RawHtmlRole $rawHtml = null): UjoBodyConverter
    {
        return new UjoBodyConverter(new DefaultCompiler(), SanitizerPurifier::defaultPolicies(), rawHtml: $rawHtml);
    }

    private function publishedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-31 16:00:00.123', new DateTimeZone('UTC'));
    }

    /**
     * @param list<string> $roles
     */
    private function authorization(array $roles): AuthorizationCheckerInterface
    {
        return new readonly class($roles) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $roles
             */
            public function __construct(
                private array $roles,
            ) {
            }

            #[Override]
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->roles, true);
            }
        };
    }
}
