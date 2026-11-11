<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A document whose body is fixed when the container is built: `did.json`, `did-configuration.json`,
 * `apple-app-site-association`, `assetlinks.json`, and the `documents` of the configuration.
 */
final readonly class StaticDocument implements WellKnownDocument
{
    public function __construct(
        private string $body,
        private string $contentType,
        private bool $cors = false,
    ) {
    }

    #[Override]
    public function serve(Request $request): Response
    {
        return DocumentResponses::body($this->body, $this->contentType, $this->cors);
    }
}
