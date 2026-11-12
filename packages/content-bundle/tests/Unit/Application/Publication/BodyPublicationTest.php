<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\ContentBundle\Tests\Unit\Fixture\WorkflowOnlyDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\WorkflowOnlyRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\MarkupFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The life of a body: a draft keeps the format its author writes in, and publishing stores on the live row the UJO
 * the draft converts into, dated with the moment of the publication, as its source, with the HTML compiled from it.
 */
#[CoversClass(ContentPublisher::class)]
#[CoversClass(BodyDataMapper::class)]
#[CoversClass(UjoBodyConverter::class)]
final class BodyPublicationTest extends TestCase
{
    private const string HEADING_AND_PARAGRAPH = '{"time":1793462400000,"blocks":[{"id":"b1","type":"header","data":{"text":"Ouverture","level":2},"tunes":{"anchor":{"anchor":"ouverture"}}},{"id":"b2","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}';

    private const string HEADING_AND_PARAGRAPH_HTML = "<h2 id=\"ouverture\">Ouverture</h2>\n<p>Le site <b>ouvre</b>.</p>";

    private InMemoryContents $contents;

    private ContentManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents(compiler: new DefaultCompiler(), purifier: SanitizerPurifier::defaultPolicies());
        $this->manager = $this->contents->manager;
    }

    #[DataProvider('formats')]
    public function testTheDraftKeepsItsFormatAndTheLiveRowStoresUjoAndItsHtml(string $format, string $source, string $draftHtml, string $ujo): void
    {
        $reference = $this->manager->create('example', 'fr', ['bodySource' => $source, 'bodyFormat' => $format]);
        $this->manager->publish($reference, 'fr');

        $draft = $this->manager->draft($reference, 'fr');
        self::assertSame($source, $draft['bodySource']);
        self::assertSame($format, $draft['bodyFormat']);
        self::assertSame($draftHtml, $draft['bodyHtml']);

        $live = $this->manager->live($reference, 'fr');
        self::assertSame($ujo, $live['bodySource']);
        self::assertSame('ujo', $live['bodyFormat']);
        self::assertSame(self::HEADING_AND_PARAGRAPH_HTML, $live['bodyHtml']);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function formats(): iterable
    {
        $bbcode = '{"time":1793462400000,"blocks":[{"id":"b2","type":"header","data":{"text":"Ouverture","level":2},"tunes":{"anchor":{"anchor":"ouverture"}}},{"id":"b3","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}';
        $ujo = '{"blocks":[{"id":"ouverture","type":"header","data":{"text":"Ouverture","level":2}},{"id":"p","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}';

        yield 'Markdown' => ['markdown', "## Ouverture\n\nLe site **ouvre**.", self::HEADING_AND_PARAGRAPH_HTML, self::HEADING_AND_PARAGRAPH];
        yield 'BBCode' => ['bbcode', "[h2]Ouverture[/h2]\n\nLe site [b]ouvre[/b].", self::HEADING_AND_PARAGRAPH_HTML, $bbcode];
        yield 'MediaWiki' => ['mediawiki', "== Ouverture ==\n\nLe site '''ouvre'''.", self::HEADING_AND_PARAGRAPH_HTML, self::HEADING_AND_PARAGRAPH];
        yield 'DokuWiki' => ['dokuwiki', "===== Ouverture =====\n\nLe site **ouvre**.", self::HEADING_AND_PARAGRAPH_HTML, self::HEADING_AND_PARAGRAPH];
        yield 'Creole' => ['creole', "== Ouverture\n\nLe site **ouvre**.", self::HEADING_AND_PARAGRAPH_HTML, self::HEADING_AND_PARAGRAPH];
        yield 'HTML, compiled as written in the draft' => ['html', '<h2 id="ouverture">Ouverture</h2><p>Le site <b>ouvre</b>.</p>', '<h2 id="ouverture">Ouverture</h2><p>Le site <b>ouvre</b>.</p>', self::HEADING_AND_PARAGRAPH];
        yield 'UJO, dated with the publication' => ['ujo', $ujo, self::HEADING_AND_PARAGRAPH_HTML, '{"time":1793462400000,'.substr($ujo, 1)];
    }

    public function testAPlainTextDraftIsPublishedAsParagraphs(): void
    {
        $reference = $this->manager->create('example', 'fr', ['bodySource' => "Ouverture\n\nLe site ouvre.", 'bodyFormat' => 'text']);
        $this->manager->publish($reference, 'fr');

        $live = $this->manager->live($reference, 'fr');
        self::assertSame('{"time":1793462400000,"blocks":[{"id":"b1","type":"paragraph","data":{"text":"Ouverture"}},{"id":"b2","type":"paragraph","data":{"text":"Le site ouvre."}}]}', $live['bodySource']);
        self::assertSame("<p>Ouverture</p>\n<p>Le site ouvre.</p>", $live['bodyHtml']);
    }

    /**
     * Editing the published draft forks a version in the author's format; the live row keeps the UJO of the first.
     */
    public function testAnEditAfterPublicationStaysInItsFormatUntilPublished(): void
    {
        $reference = $this->manager->create('example', 'fr', ['bodySource' => 'Le site **ouvre**.']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['bodySource' => '[b]Fermé[/b]', 'bodyFormat' => 'bbcode']);

        self::assertSame('bbcode', $this->manager->draft($reference, 'fr')['bodyFormat']);
        self::assertSame('<p>Le site <b>ouvre</b>.</p>', $this->manager->live($reference, 'fr')['bodyHtml']);

        $this->manager->publish($reference, 'fr');

        self::assertSame('<p><b>Fermé</b></p>', $this->manager->live($reference, 'fr')['bodyHtml']);
        self::assertSame('bbcode', $this->manager->draft($reference, 'fr')['bodyFormat']);
    }

    /**
     * Removing the draft abandons the changes made since the publication: the draft holds again the text its author
     * published, in its own format, while the live row keeps the UJO.
     */
    #[DataProvider('publishedTexts')]
    public function testRemovingTheDraftPutsTheTextItsAuthorPublishedBack(string $format, string $source): void
    {
        $reference = $this->manager->create('example', 'fr', ['bodySource' => $source, 'bodyFormat' => $format]);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['bodySource' => '[b]Brouillon[/b]', 'bodyFormat' => 'bbcode']);
        $this->manager->removeDraft($reference, 'fr');

        $draft = $this->manager->draft($reference, 'fr');
        self::assertSame($source, $draft['bodySource']);
        self::assertSame($format, $draft['bodyFormat']);
        self::assertSame(self::HEADING_AND_PARAGRAPH_HTML, $draft['bodyHtml']);
        self::assertSame('published', $draft['workflowPlace']);
        self::assertSame('ujo', $this->manager->live($reference, 'fr')['bodyFormat']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function publishedTexts(): iterable
    {
        yield 'Markdown' => ['markdown', "## Ouverture\n\nLe site **ouvre**."];
        yield 'BBCode' => ['bbcode', "[h2]Ouverture[/h2]\n\nLe site [b]ouvre[/b]."];
    }

    /**
     * Without the frozen draft row of the published version — history pruned, or a locale published before the UJO
     * conversion existed — the live row is put back, its body in UJO.
     */
    public function testRemovingTheDraftPutsTheLiveRowBackWithoutTheFrozenDraftRow(): void
    {
        $reference = $this->manager->create('example', 'fr', ['bodySource' => 'Le site **ouvre**.']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['bodySource' => 'Brouillon']);

        $frozen = $this->contents->rows->find($this->root($reference), DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
        self::assertInstanceOf(ExampleDimensionContent::class, $frozen);
        $this->contents->rows->remove($frozen);
        $this->contents->rows->flush();

        $this->manager->removeDraft($reference, 'fr');

        $draft = $this->manager->draft($reference, 'fr');
        self::assertSame('ujo', $draft['bodyFormat']);
        self::assertSame('{"time":1793462400000,"blocks":[{"id":"b1","type":"paragraph","data":{"text":"Le site <b>ouvre</b>."}}]}', $draft['bodySource']);
        self::assertSame('published', $draft['workflowPlace']);
    }

    /**
     * A body written around the data mapper that cannot be read is refused before anything changes.
     */
    public function testABodyThatCannotBeConvertedLeavesTheLocaleUnpublished(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Bonjour']);
        $row = $this->contents->rows->find($this->root($reference), DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
        self::assertInstanceOf(ExampleDimensionContent::class, $row);
        $row->setBody(Body::of('{"blocks":[{"id":"a","type":"nope","data":{}}]}', MarkupFormat::Ujo, ''));

        try {
            $this->manager->publish($reference, 'fr');
            self::fail('The publication is refused.');
        } catch (InvalidContent $invalidContent) {
            self::assertSame('The ujo source cannot be read: The block type "nope" at blocks[0] is not registered.', $invalidContent->getMessage());
        }

        self::assertSame('unpublished', $this->manager->status($reference)[0]->place);
        self::assertNull($this->contents->rows->latestLocalizedVersion($this->root($reference), Stage::Live, Locale::fromString('fr')));
    }

    public function testADraftWithoutABodyIsPublishedWithout(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Bonjour']);
        $this->manager->publish($reference, 'fr');

        self::assertNull($this->manager->live($reference, 'fr')['bodySource']);
    }

    public function testARowWithoutTheBodyCapabilityIsPublished(): void
    {
        $root = new WorkflowOnlyRoot();
        $this->contents->rows->store(new WorkflowOnlyDimensionContent($root, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first())));

        $this->contents->publisher->publish($root, Locale::fromString('fr'));

        $this->contents->transaction->flush();

        self::assertInstanceOf(WorkflowOnlyDimensionContent::class, $this->contents->rows->find($root, DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first())));
    }

    private function root(ContentReference $reference): ExampleRoot
    {
        $root = $this->contents->roots->find($reference);
        self::assertInstanceOf(ExampleRoot::class, $root);

        return $root;
    }
}
