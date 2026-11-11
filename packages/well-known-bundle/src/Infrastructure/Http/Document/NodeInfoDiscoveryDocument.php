<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `/.well-known/nodeinfo`: the NodeInfo discovery document, a link to the 2.1 document by its absolute URL.
 */
final readonly class NodeInfoDiscoveryDocument implements WellKnownDocument
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private string $route,
    ) {
    }

    #[Override]
    public function serve(Request $request): Response
    {
        return DocumentResponses::json(
            ['links' => [['rel' => NodeInfo::SCHEMA, 'href' => $this->urls->generate($this->route, [], UrlGeneratorInterface::ABSOLUTE_URL)]]],
            'application/json',
            true,
        );
    }
}
