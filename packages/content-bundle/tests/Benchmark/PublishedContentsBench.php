<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Benchmark;

use Generator;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Tests\Benchmark\Fixture\ContentDatabase;
use Phil\LocaleDimensions\Locale;

/**
 * The published contents of a locale feed the sitemap, which walks every one of them, and the feeds, which read the
 * latest few on every request: both hydrate and merge every row they return.
 *
 * The entity manager is cleared before each read, as a new request starts with an empty identity map.
 *
 * @BeforeMethods("setUp")
 *
 * @Iterations(5)
 */
final readonly class PublishedContentsBench
{
    private ContentDatabase $database;

    private PublishedContents $published;

    private Locale $locale;

    public function __construct()
    {
        $this->database = new ContentDatabase();
        $this->published = $this->database->published;
        $this->locale = Locale::fromString('fr');
    }

    /**
     * @param array{articles: int} $params
     */
    public function setUp(array $params): void
    {
        $this->database->store($params['articles']);
    }

    /**
     * Every article of the locale, in chunks of 500: what the sitemap reads.
     *
     * @ParamProviders("sizes")
     *
     * @Revs(3)
     */
    public function benchChunks(): void
    {
        $this->database->entityManager->clear();

        iterator_count($this->published->chunks('article', $this->locale));
    }

    /**
     * The 20 latest articles with a slug: what a feed reads.
     *
     * @ParamProviders("sizes")
     *
     * @Revs(20)
     */
    public function benchLatest(): void
    {
        $this->database->entityManager->clear();
        $this->published->latest('article', $this->locale, 20, true);
    }

    /**
     * @return Generator<string, array{articles: int}>
     */
    public function sizes(): Generator
    {
        yield '10 articles' => ['articles' => 10];
        yield '100 articles' => ['articles' => 100];
        yield '1000 articles' => ['articles' => 1_000];
    }
}
