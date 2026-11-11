<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/.well-known/atproto-did`: the DID an AT Protocol handle resolves to, proving the handle by its domain. The DID of
 * the host asked for when the configuration names one for it, else the site's; a `404` when neither is set.
 */
final readonly class AtprotoDidDocument implements WellKnownDocument
{
    /**
     * @param array<string, string> $hosts the DID of each host, keyed by the host in lowercase, as the request gives it
     */
    public function __construct(
        private ?string $did,
        private array $hosts = [],
    ) {
    }

    #[Override]
    public function serve(Request $request): Response
    {
        $did = $this->hosts[$request->getHost()] ?? $this->did;

        if (null === $did) {
            $response = DocumentResponses::body('', 'text/plain; charset=utf-8', true);
            $response->setStatusCode(Response::HTTP_NOT_FOUND);

            return $response;
        }

        return DocumentResponses::body($did, 'text/plain; charset=utf-8', true);
    }
}
