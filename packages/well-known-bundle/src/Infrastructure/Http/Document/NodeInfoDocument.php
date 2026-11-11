<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\NodeInfoProvider;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The NodeInfo 2.1 document, at `/nodeinfo/2.1` by default: the protocols and services of the configuration, the rest
 * from the provider, asked on each request.
 */
final readonly class NodeInfoDocument implements WellKnownDocument
{
    public const string CONTENT_TYPE = 'application/json; profile="'.NodeInfo::SCHEMA.'#"';

    /**
     * @param list<string> $protocols
     * @param list<string> $inbound
     * @param list<string> $outbound
     */
    public function __construct(
        private NodeInfoProvider $provider,
        private array $protocols,
        private array $inbound = [],
        private array $outbound = [],
    ) {
    }

    #[Override]
    public function serve(Request $request): Response
    {
        $nodeInfo = new NodeInfo(
            $this->provider->software(),
            $this->protocols,
            $this->inbound,
            $this->outbound,
            $this->provider->openRegistrations(),
            $this->provider->usage(),
            $this->provider->metadata(),
        );

        return DocumentResponses::json($nodeInfo->toArray(), self::CONTENT_TYPE, true);
    }
}
