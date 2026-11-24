<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Writer;

use Phil\FeedBundle\Application\Writer\JsonFeedWriter;
use Phil\FeedBundle\Application\Writer\XmlText;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Tests\Fixtures\Entries;
use Phil\FeedBundle\Tests\Fixtures\JsonFeedSchema;
use Phil\FeedBundle\Tests\Fixtures\SampleFeeds;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFeedWriter::class)]
#[CoversClass(XmlText::class)]
final class JsonFeedWriterTest extends TestCase
{
    public function testAFullFeedIsWrittenAsExpected(): void
    {
        $json = new JsonFeedWriter()->write(SampleFeeds::full(), SampleFeeds::links());

        self::assertStringEqualsFile(__DIR__.'/../../Fixtures/full.json', $json);
        JsonFeedSchema::assertValid($json);
        self::assertSame(FeedFormat::Json, new JsonFeedWriter()->format());
    }

    public function testAnEmptyFeedIsValid(): void
    {
        $json = new JsonFeedWriter()->write(SampleFeeds::empty(), SampleFeeds::plainLinks());

        self::assertStringEqualsFile(__DIR__.'/../../Fixtures/empty.json', $json);
        JsonFeedSchema::assertValid($json);
    }

    public function testAnItemWithoutContentHasItsSummaryOrItsTitleAsText(): void
    {
        $json = new JsonFeedWriter()->write(Entries::feed(Entries::item(summary: 'Short'), Entries::item('Bare')), SampleFeeds::plainLinks());
        JsonFeedSchema::assertValid($json);
        $items = $this->items($json);

        self::assertSame(['Short', 'Short'], [$items[0]['content_text'] ?? null, $items[0]['summary'] ?? null]);
        self::assertSame('Bare', $items[1]['content_text'] ?? null);
        self::assertArrayNotHasKey('summary', $items[1]);
        self::assertArrayNotHasKey('content_html', $items[1]);
    }

    public function testAnAuthorKeepsOnlyWhatItHas(): void
    {
        $items = $this->items(new JsonFeedWriter()->write(Entries::feed(Entries::item(authors: [new Author('Ada', avatar: Url::fromString('https://example.org/ada.png')), new Author('Bob', Url::fromString('https://example.org/bob'))])), SampleFeeds::plainLinks()));

        self::assertSame([['name' => 'Ada', 'avatar' => 'https://example.org/ada.png'], ['name' => 'Bob', 'url' => 'https://example.org/bob']], $items[0]['authors'] ?? null);
    }

    public function testAnItemWithContentHasNoText(): void
    {
        $items = $this->items(new JsonFeedWriter()->write(Entries::feed(Entries::item(summary: 'Short', content: '<p>Long</p>')), SampleFeeds::plainLinks()));

        self::assertSame('<p>Long</p>', $items[0]['content_html'] ?? null);
        self::assertArrayNotHasKey('content_text', $items[0]);
    }

    public function testAnAttachmentKeepsOnlyWhatItKnows(): void
    {
        $item = Entries::item(attachment: new Attachment(Url::fromString('https://example.org/a.ogg'), 'audio/ogg'));
        $items = $this->items(new JsonFeedWriter()->write(Entries::feed($item), SampleFeeds::plainLinks()));

        self::assertSame([['url' => 'https://example.org/a.ogg', 'mime_type' => 'audio/ogg']], $items[0]['attachments'] ?? null);
    }

    public function testControlCharactersAreEscaped(): void
    {
        $json = new JsonFeedWriter()->write(Entries::feed(Entries::item(content: "<p>x\u{1}y</p>")), SampleFeeds::plainLinks());

        self::assertStringContainsString('"content_html": "<p>x\u0001y</p>"', $json);
        self::assertStringEndsWith("}\n", $json);
    }

    /**
     * @return list<array<mixed>>
     */
    private function items(string $json): array
    {
        $document = json_decode($json, true, 32, \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertIsList($document['items']);
        $items = [];

        foreach ($document['items'] as $item) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        return $items;
    }
}
