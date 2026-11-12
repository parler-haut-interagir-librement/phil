<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A manager built without its optional collaborators — the page events, the single kinds, the working copies — does
 * its job without them, and one built with the working copies removes them with the content.
 */
#[CoversClass(ContentManager::class)]
final class ContentManagerOptionalPortsTest extends TestCase
{
    private InMemoryContents $contents;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents();
    }

    public function testWithoutPageEventsAContentIsCreatedPublishedUnpublishedAndDeleted(): void
    {
        $manager = $this->contents->managerWith();
        $reference = $manager->create('example', 'fr', ['slug' => 'ouverture']);

        $manager->publish($reference, 'fr');
        self::assertSame('published', $manager->status($reference)[0]->place);

        $manager->unpublish($reference, 'fr');
        self::assertSame('unpublished', $manager->status($reference)[0]->place);

        $manager->delete($reference);
        self::assertSame([], $manager->list('example'));
    }

    public function testWithoutSingleKindsNoKindIsSingle(): void
    {
        $manager = $this->contents->managerWith();

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('"example" is not a single kind; declare it under "phil_content.singles".');

        $manager->single('example');
    }

    public function testDeletingAContentRemovesItsWorkingCopies(): void
    {
        $copies = new class implements WorkingCopyRepository {
            /** @var list<WorkingCopy> */
            public array $kept = [];

            #[Override]
            public function findAutosave(ContentReference $content, Locale $locale, string $author): ?WorkingCopy
            {
                return null;
            }

            #[Override]
            public function findNamed(ContentReference $content, Locale $locale, string $name): ?WorkingCopy
            {
                return null;
            }

            #[Override]
            public function findAllNamed(ContentReference $content, Locale $locale): array
            {
                return [];
            }

            #[Override]
            public function findAllOf(ContentReference $content): array
            {
                return array_values(array_filter($this->kept, static fn (WorkingCopy $copy): bool => $copy->content()->equals($content)));
            }

            #[Override]
            public function add(WorkingCopy $copy): void
            {
                $this->kept[] = $copy;
            }

            #[Override]
            public function remove(WorkingCopy $copy): void
            {
                $this->kept = array_values(array_filter($this->kept, static fn (WorkingCopy $kept): bool => $kept !== $copy));
            }
        };
        $manager = $this->contents->managerWith(workingCopies: $copies);
        $first = $manager->create('example', 'fr', []);
        $second = $manager->create('example', 'fr', []);
        $now = new DateTimeImmutable('2026-11-11 12:00 UTC');
        $copies->add(WorkingCopy::autosave($first, Locale::fromString('fr'), 'alice', [], $now));
        $copies->add(WorkingCopy::named($first, Locale::fromString('en'), 'court', 'bob', [], $now));
        $copies->add(WorkingCopy::autosave($second, Locale::fromString('fr'), 'alice', [], $now));

        $manager->delete($first);

        self::assertCount(1, $copies->kept);
        self::assertTrue($copies->kept[0]->content()->equals($second));
    }
}
