<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Http;

use Phil\FeedBundle\Application\Contract\FeedWriter;
use Phil\FeedBundle\Application\Exception\UnknownFeed;
use Phil\FeedBundle\Application\FeedLinkBuilder;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Application\FeedWriters;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A feed in one format: `/feeds/{name}.{format}`, and `?page=2` for an older page of a paged feed.
 *
 * - An unknown feed, a format the application does not serve, a page that is not 2 to 1000 written plainly, and a page
 *   past the last are a `404`.
 * - The answer has the media type of its format in UTF-8, `X-Content-Type-Options: nosniff`, an `ETag` from the format
 *   and the fingerprint of the feed, and the latest change of its entries as `Last-Modified`; a request that already
 *   holds it gets a `304`, before the feed is written.
 * - It may be kept `$maxAge` seconds by browsers and shared caches, and `$sharedMaxAge` by shared caches; 0 keeps it in
 *   none.
 */
final readonly class FeedController
{
    private const string PAGE = '/^[1-9][0-9]{0,3}$/D';

    public function __construct(
        private Feeds $feeds,
        private FeedWriters $writers,
        private FeedLinkBuilder $links,
        private int $maxAge = 900,
        private ?int $sharedMaxAge = null,
    ) {
    }

    public function __invoke(Request $request, string $name, string $format): Response
    {
        $feedFormat = FeedFormat::tryFrom($format);
        $writer = null === $feedFormat ? null : $this->writers->for($feedFormat);

        if (null === $feedFormat || !$writer instanceof FeedWriter) {
            throw new NotFoundHttpException(\sprintf('The feeds are not served as "%s".', mb_substr($format, 0, 16)));
        }

        $page = $this->page($request);

        try {
            $feed = $this->feeds->feed($name, $page);
        } catch (UnknownFeed $unknownFeed) {
            throw new NotFoundHttpException($unknownFeed->getMessage(), $unknownFeed);
        }

        if ($page > 1 && $feed->isEmpty()) {
            throw new NotFoundHttpException(\sprintf('The feed "%s" has no page %d.', $name, $page));
        }

        $response = new Response('', Response::HTTP_OK, ['Content-Type' => $feedFormat->contentType(), 'X-Content-Type-Options' => 'nosniff']);
        $response->setEtag(hash('xxh128', $feedFormat->value."\0".$feed->fingerprint()));

        if (!$feed->isEmpty()) {
            $response->setLastModified($feed->updated);
        }

        if ($this->maxAge > 0) {
            $response->setPublic();
            $response->setMaxAge($this->maxAge);
        }

        if (null !== $this->sharedMaxAge && $this->sharedMaxAge > 0) {
            $response->setPublic();
            $response->setSharedMaxAge($this->sharedMaxAge);
        }

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response->setContent($writer->write($feed, $this->links->links($name, $feedFormat, $feed)));
    }

    private function page(Request $request): int
    {
        if (!$request->query->has('page')) {
            return 1;
        }

        $page = $request->query->all()['page'];

        if (!\is_string($page) || 1 !== preg_match(self::PAGE, $page) || (int) $page < 2 || (int) $page > FeedQuery::MAX_PAGE) {
            throw new NotFoundHttpException('A page of a feed is a number from 2 to 1000.');
        }

        return (int) $page;
    }
}
