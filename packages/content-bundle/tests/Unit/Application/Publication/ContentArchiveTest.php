<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Phil\ContentBundle\Application\Normalizer\ExcerptNormalizer;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\ArchivedVersion;
use Phil\ContentBundle\Application\Publication\ContentArchive;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentArchive::class)]
#[CoversClass(ArchivedVersion::class)]
final class ContentArchiveTest extends TestCase
{
    public function testTheHistoryListsThePublicationsOfTheLocaleLatestFirst(): void
    {
        $contents = new InMemoryContents();
        $reference = $contents->manager->create('example', 'fr', ['excerptTitle' => 'Un']);
        $contents->manager->publish($reference, 'fr');
        $contents->clock->modify('+1 day');
        $contents->manager->edit($reference, 'fr', ['excerptTitle' => 'Deux']);
        $contents->manager->edit($reference, 'en', ['excerptTitle' => 'Two']);
        $contents->manager->publish($reference, 'fr');
        $contents->manager->edit($reference, 'fr', ['excerptTitle' => 'Trois']);

        $archive = $this->archive($contents);
        $history = $archive->history($reference, Locale::fromString('fr'));

        self::assertSame([2, 1], array_map(static fn (ArchivedVersion $published): int => $published->version, $history));
        self::assertSame('2026-11-01 17:00', $history[0]->publishedAt->format('Y-m-d H:i'));
        self::assertSame('UTC', $history[0]->publishedAt->getTimezone()->getName());
        self::assertSame([], $archive->history($reference, Locale::fromString('en')), 'A locale never published has no archive.');
        self::assertSame('Un', $archive->read($reference, Locale::fromString('fr'), 1)['excerptTitle']);
        self::assertSame('Deux', $archive->read($reference, Locale::fromString('fr'), 2)['excerptTitle']);
        self::assertTrue($archive->isArchived('example'));
        self::assertFalse($archive->isArchived('news'));
    }

    public function testAVersionNeverPublishedInTheLocaleIsNotFound(): void
    {
        $contents = new InMemoryContents();
        $reference = $contents->manager->create('example', 'fr', ['excerptTitle' => 'Un']);
        $contents->manager->edit($reference, 'en', ['excerptTitle' => 'One']);
        $contents->manager->publish($reference, 'fr');

        $this->expectException(ContentNotFound::class);

        $this->archive($contents)->read($reference, Locale::fromString('en'), 1);
    }

    public function testAVersionNumberBelowOneIsNotFound(): void
    {
        $contents = new InMemoryContents();
        $reference = $contents->manager->create('example', 'fr', ['excerptTitle' => 'Un']);

        $this->expectException(ContentNotFound::class);

        $this->archive($contents)->read($reference, Locale::fromString('fr'), 0);
    }

    public function testAMissingRootIsNotFound(): void
    {
        $this->expectException(ContentNotFound::class);

        $this->archive(new InMemoryContents())->history(ContentReference::of('example', '404'), Locale::fromString('fr'));
    }

    public function testAKindWithoutPublicArchiveIsRefused(): void
    {
        $contents = new InMemoryContents();
        $reference = $contents->manager->create('example', 'fr', ['excerptTitle' => 'Un']);
        $contents->manager->publish($reference, 'fr');

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('"example" has no public archive; declare it under "phil_content.archives".');

        new ContentArchive($contents->roots, $contents->rows, $contents->aggregator, new ContentNormalizer([]))->read($reference, Locale::fromString('fr'), 1);
    }

    private function archive(InMemoryContents $contents): ContentArchive
    {
        return new ContentArchive($contents->roots, $contents->rows, $contents->aggregator, new ContentNormalizer([new ExcerptNormalizer()]), ['example']);
    }
}
