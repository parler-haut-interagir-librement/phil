<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Infrastructure\Search\ContentDocumentProvider;
use Phil\ContentBundle\Tests\Functional\App\Entity\NewsDimension;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The published contents in `phil/search-bundle`'s index, kept up to date at each flush: what readers see is found,
 * in the passage of its own language, and nothing else is.
 */
#[CoversClass(ContentDocumentProvider::class)]
final class SearchFeedTest extends FunctionalTestCase
{
    public function testAPublishedContentIsFoundAndAnUnpublishedOneIsNot(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture', 'bodySource' => 'Le site ouvre ses portes aux lutins.']);

        self::assertSame([], $this->find('lutins'), 'A draft is not found.');

        $this->contents()->publish($news, 'fr');

        self::assertSame(['news-'.$news->resourceId.'-fr'], $this->find('lutins'));
        self::assertSame(['news-'.$news->resourceId.'-fr'], $this->find('ouverture'), 'The title is searched.');
        self::assertSame([], $this->find('lutins', ['locale' => 'en']));
        self::assertSame(['news-'.$news->resourceId.'-fr'], $this->find('lutins', ['kind' => 'news', 'reference' => $news->toString()]));

        $this->contents()->unpublish($news, 'fr');

        self::assertSame([], $this->find('lutins'));
    }

    public function testAPassageInAnotherLanguageIsIndexedInItsOwnField(): void
    {
        $ujo = '{"time":1,"blocks":[{"id":"a1","type":"paragraph","data":{"text":"Des lutins."}},{"id":"a2","type":"paragraph","data":{"text":"Some elves."},"tunes":{"language":{"language":"EN"}}}],"version":"2.31.0"}';
        $news = $this->contents()->create('news', 'fr_CA', ['slug' => 'citation', 'bodyFormat' => 'ujo', 'bodySource' => $ujo]);
        $this->contents()->publish($news, 'fr_CA');

        $id = 'news-'.$news->resourceId.'-fr_CA';
        self::assertSame([$id], $this->find('elves'));
        self::assertSame([$id], $this->find('lutins'));
        $document = $this->service(ContentDocumentProvider::class)->document($this->liveRow());
        self::assertNotNull($document);
        self::assertSame(['body' => 'Des lutins.', 'body_en' => 'Some elves.'], $document->text);
    }

    public function testANoIndexContentIsNotFound(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'cachee', 'excerptTitle' => 'Cachée', 'seoNoIndex' => true, 'bodySource' => 'Des farfadets.']);
        $this->contents()->publish($news, 'fr');

        self::assertSame([], $this->find('farfadets'));
    }

    public function testTheIndexIsRebuiltFromEveryLiveRow(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'un', 'excerptTitle' => 'Un', 'bodySource' => 'Des korrigans.']);
        $this->contents()->edit($news, 'en', ['slug' => 'one', 'excerptTitle' => 'One', 'bodySource' => 'Some korrigans.']);
        $this->contents()->publish($news, 'fr');
        $this->contents()->publish($news, 'en');
        $this->contents()->create('static_page', 'fr', ['slug' => 'brouillon', 'bodySource' => 'Des korrigans en brouillon.']);

        self::assertSame(2, $this->service(Search::class)->reindex('content'));
        self::assertCount(2, $this->find('korrigans'));
    }

    private function liveRow(): DimensionContent
    {
        foreach ($this->entityManager()->getRepository(NewsDimension::class)->findAll() as $row) {
            if (Stage::Live === $row->dimension()->stage && $row->dimension()->isLocalized()) {
                return $row;
            }
        }

        self::fail('No live row.');
    }

    /**
     * @param array<string, scalar> $filters
     *
     * @return list<string>
     */
    private function find(string $text, array $filters = []): array
    {
        return $this->service(Search::class)->search('content', SearchQuery::of($text, $filters))->ids;
    }
}
