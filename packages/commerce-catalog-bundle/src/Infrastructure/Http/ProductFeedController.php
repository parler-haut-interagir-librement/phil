<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Http;

use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\FeedDefinition;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\ProductFeeds;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the file of a feed the configuration declares, for a feed written outside the public directory: its media
 * type, a `Last-Modified` date and an entity tag made of the file's time and size — never of its content, which may
 * weigh hundreds of megabytes —, kept public by caches for the feed's `max_age`. A reader that sends the tag or the
 * date back is answered `304 Not Modified`.
 *
 * A feed not declared, or not yet published, is not found. The file is read where `path` puts it, never from the
 * request: only the feed's name comes from the address.
 */
final readonly class ProductFeedController
{
    public const string ROUTE = 'phil_commerce_catalog_product_feed';

    public function __construct(
        private ProductFeeds $feeds,
    ) {
    }

    public function __invoke(Request $request, string $feed): BinaryFileResponse
    {
        $definition = $this->feeds->definition($feed);

        if (!$definition instanceof FeedDefinition || !is_file($definition->path)) {
            throw new NotFoundHttpException(\sprintf('No feed "%s" is published.', $feed));
        }

        // Public, its Last-Modified read from the file: the defaults of BinaryFileResponse.
        $response = new BinaryFileResponse($definition->path, headers: ['Content-Type' => $definition->format->mediaType(), 'X-Content-Type-Options' => 'nosniff']);
        $file = $response->getFile();
        $response->setEtag(hash('xxh128', $file->getMTime().':'.$file->getSize()));
        $response->setMaxAge($definition->maxAge);
        $response->isNotModified($request);

        return $response;
    }
}
