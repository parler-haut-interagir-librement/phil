<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Sitemap;

use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Application\Genres;
use Phil\DirectoryBundle\Infrastructure\Routing\DirectoryRoutes;
use Phil\IndexingConsent\Reader;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The pages of the directory in the sitemap, one section per genre: its list, and each listing published in the
 * locale and left listed by its author, with when it was last published, read in chunks of `CHUNK_SIZE`. The section
 * is the genre's key, `season_2` written `season-2` (`Sitemap::sectionNamed()`).
 */
final readonly class DirectoryUrlProvider implements UrlProvider
{
    /**
     * How many listings of a genre are read at once.
     */
    public const int CHUNK_SIZE = 500;

    public function __construct(
        private Directory $directory,
        private Genres $genres,
        private UrlGeneratorInterface $urls,
        private string $locale,
        private bool $allowLocalhost,
    ) {
    }

    public function provide(UrlContainer $urls, ?string $section): void
    {
        foreach ($this->genres->all() as $genre) {
            $name = Sitemap::sectionNamed($genre->key);

            if (null !== $section && $section !== $name) {
                continue;
            }

            $urls->add(new Url($this->urls->generate(DirectoryRoutes::name($genre->key, 'index'), [], UrlGeneratorInterface::ABSOLUTE_URL), allowLocalhost: $this->allowLocalhost), $name);

            for ($offset = 0;; $offset += self::CHUNK_SIZE) {
                $listings = $this->directory->published($genre->key, $this->locale, $offset, self::CHUNK_SIZE, Reader::Sitemap);

                foreach ($listings as $listing) {
                    $slug = $listing->content instanceof RoutableCapable ? $listing->content->getSlug()?->value : null;

                    if (null !== $slug && !$listing->isHiddenFromSitemaps()) {
                        $urls->add(new Url($this->urls->generate(DirectoryRoutes::name($genre->key, 'show'), ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL), $listing->publishedAt(), allowLocalhost: $this->allowLocalhost), $name);
                    }
                }

                if (\count($listings) < self::CHUNK_SIZE) {
                    break;
                }
            }
        }
    }
}
