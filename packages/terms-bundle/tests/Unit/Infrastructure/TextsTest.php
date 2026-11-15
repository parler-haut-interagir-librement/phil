<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolicyTextRenderer::class)]
final class TextsTest extends TestCase
{
    private const string DOCUMENT = '{"blocks":[{"id":"a1","type":"paragraph","data":{"text":"Hello <b>world</b>"}}]}';

    public function testAPlainTextIsEscapedInParagraphs(): void
    {
        $html = new PolicyTextRenderer()->toHtml($this->version("\n\n First <line>\r\nsecond & \"third\" 'fourth'\r\n \r\n\n\nNext\rparagraph \n\n"), 'en', 'en');

        self::assertSame("<p>First &lt;line&gt;<br>\nsecond &amp; &quot;third&quot; &apos;fourth&apos;</p>\n<p>Next<br>\nparagraph</p>", $html);
    }

    public function testTheTextOfTheLocaleIsRendered(): void
    {
        $version = $this->version('Hello', ['en' => 'Hello', 'fr' => 'Bonjour']);

        self::assertSame('<p>Bonjour</p>', new PolicyTextRenderer()->toHtml($version, 'fr', 'en'));
        self::assertSame('<p>Hello</p>', new PolicyTextRenderer()->toHtml($version, 'de', 'en'));
    }

    public function testADocumentIsRenderedByUjo(): void
    {
        $version = $this->version(self::DOCUMENT, format: TextFormat::Ujo);

        self::assertSame(new Ujo()->toHtml(self::DOCUMENT), new PolicyTextRenderer(new Ujo())->toHtml($version, 'en', 'en'));
        self::assertStringContainsString('<b>world</b>', new PolicyTextRenderer(new Ujo())->toHtml($version, 'en', 'en'));
        self::assertStringStartsWith('<p>{&quot;blocks&quot;', new PolicyTextRenderer()->toHtml($version, 'en', 'en'));
    }

    #[DoesNotPerformAssertions]
    public function testAPlainTextNeedsNoCheck(): void
    {
        new PolicyTextRenderer()->check(TextFormat::Text, 'anything');
    }

    public function testADocumentIsCheckedByUjo(): void
    {
        new PolicyTextRenderer(new Ujo())->check(TextFormat::Ujo, self::DOCUMENT);

        try {
            new PolicyTextRenderer(new Ujo())->check(TextFormat::Ujo, '{"blocks":');
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('invalid_document', $invalidTerms->reason);
            self::assertNotNull($invalidTerms->getPrevious());
            self::assertSame('The text is not a UJO document: '.$invalidTerms->getPrevious()->getMessage(), $invalidTerms->getMessage());
            self::assertNotSame('', $invalidTerms->getPrevious()->getMessage());
        }
    }

    public function testADocumentNeedsUjo(): void
    {
        $this->expectExceptionObject(InvalidTerms::because('format_unavailable', 'The "ujo" format needs phil/ujo.'));

        new PolicyTextRenderer()->check(TextFormat::Ujo, self::DOCUMENT);
    }

    public function testTheChangesBetweenTwoVersionsAreMarked(): void
    {
        $old = $this->version("One rule.\n\nAnother rule.");
        $new = $this->version("One new rule.\n\nAnother rule.", number: 3);

        self::assertSame("<p>One <ins>new </ins>rule.</p>\n<p>Another rule.</p>", new PolicyTextRenderer(differ: new TextDiff())->changes($old, $new, 'en', 'en'));
        self::assertNull(new PolicyTextRenderer()->changes($old, $new, 'en', 'en'));
    }

    /**
     * @param array<string, string>|null $texts
     */
    private function version(string $text, ?array $texts = null, TextFormat $format = TextFormat::Text, int $number = 2): PolicyVersion
    {
        return new PolicyVersion('terms', $number, $texts ?? ['en' => $text], $format, new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-01'), true);
    }
}
