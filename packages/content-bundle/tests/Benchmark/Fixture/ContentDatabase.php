<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Benchmark\Fixture;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineDimensionContentRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrinePublishedRowRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineRouteRepository;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Page;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\PageDimension;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\MarkupFormat;
use Phil\Slug\Slug;

/**
 * Published articles on SQLite in memory, as the bundle's storage tests store them: each article has a shared live
 * row and a live row in each locale, with a title, a slug, a body and a publication date, and a current route in each
 * locale.
 */
final readonly class ContentDatabase
{
    public EntityManager $entityManager;

    public PublishedContents $published;

    public DoctrineRouteRepository $routes;

    public function __construct()
    {
        $this->entityManager = EntityManagerFactory::create(roots: [Article::class, Page::class]);
        $classes = array_map(
            $this->entityManager->getClassMetadata(...),
            [Article::class, ArticleDimension::class, Page::class, PageDimension::class, Route::class],
        );
        new SchemaTool($this->entityManager)->createSchema($classes);

        $roots = ContentRoots::fromClassNames([Article::class, Page::class]);
        $rows = new DoctrineDimensionContentRepository($this->entityManager);
        $this->routes = new DoctrineRouteRepository($this->entityManager);
        $this->published = new PublishedContents(
            new DoctrinePublishedRowRepository($this->entityManager, $roots),
            new DoctrineContentRootRepository($this->entityManager, $roots),
            $rows,
            new ContentAggregator($rows, InMemoryContents::mergers()),
        );
    }

    /**
     * Stores the articles, then clears the entity manager.
     *
     * @param list<string> $locales
     */
    public function store(int $articles, array $locales = ['fr', 'en']): void
    {
        $first = Version::first();
        $html = '<p>'.str_repeat('A paragraph of the article, with <strong>some</strong> words. ', 20).'</p>';

        for ($index = 1; $index <= $articles; ++$index) {
            $article = new Article();
            $this->entityManager->persist($article);
            $reference = ContentReference::of('article', $article->id()->toString());
            $published = new DateTimeImmutable('2026-01-01 12:00')->modify(\sprintf('+%d hours', $index));

            $this->entityManager->persist($article->createDimensionContent(DimensionKey::unlocalized(Stage::Live, $first)));

            foreach ($locales as $code) {
                $locale = Locale::fromString($code);
                $slug = Slug::fromString(\sprintf('article-%d-%s', $index, $code));
                $row = $article->createDimensionContent(DimensionKey::localized($locale, Stage::Live, $first));

                if (!$row instanceof ArticleDimension) {
                    throw new LogicException('An article has rows of ArticleDimension.');
                }

                $row->setExcerptTitle(\sprintf('Article %d (%s)', $index, $code));
                $row->setExcerptDescription('What the article is about.');
                $row->setSlug($slug);
                $row->setBody(Body::of('A paragraph.', MarkupFormat::Html, $html));
                $row->setWorkflowPlace('published');
                $row->setWorkflowPublished($published);
                $this->entityManager->persist($row);
                $this->routes->add(Route::current($reference, $locale, $slug));
            }

            if (0 === $index % 200) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
