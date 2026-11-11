<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http;

use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves every well-known document from one controller: the route names its document by `_phil_well_known`.
 *
 * Each answer carries `X-Robots-Tag: noindex`. A successful answer that sets no `max-age` nor `no-store` of its own may be
 * kept `$maxAge` seconds by browsers and shared caches, carries a hash of its body as `ETag`, and is a `304` to a request
 * that already holds it. A `$maxAge` of `0` keeps it in no shared cache.
 */
final readonly class WellKnownController
{
    public const string DOCUMENT = '_phil_well_known';

    /**
     * @param ServiceLocator<mixed> $documents the documents, keyed by the name of their route
     */
    public function __construct(
        private ServiceLocator $documents,
        private int $maxAge,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $name = $request->attributes->get(self::DOCUMENT);
        $document = \is_string($name) && $this->documents->has($name) ? $this->documents->get($name) : null;

        if (!$document instanceof WellKnownDocument) {
            throw new NotFoundHttpException('No well-known document is served here.');
        }

        $response = $document->serve($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        if (!$response->isSuccessful() || null !== $response->getMaxAge() || $response->headers->hasCacheControlDirective('no-store')) {
            return $response;
        }

        if ($this->maxAge > 0) {
            $response->setPublic();
            $response->setMaxAge($this->maxAge);
        }

        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
