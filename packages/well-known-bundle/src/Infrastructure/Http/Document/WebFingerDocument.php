<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Phil\WellKnownBundle\Application\WebFinger;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/.well-known/webfinger` (RFC 7033): the JSON Resource Descriptor of the `resource` asked for, with the links of the
 * `rel` asked for, readable by the browsers of other sites.
 *
 * A query without one valid resource is a `400`; a resource no resolver knows, whatever its scheme, a `404` with the
 * same empty body, so that the answer tells no more than the resolvers do.
 */
final readonly class WebFingerDocument implements WellKnownDocument
{
    public const string CONTENT_TYPE = 'application/jrd+json';

    public function __construct(
        private WebFinger $webFinger,
    ) {
    }

    #[Override]
    public function serve(Request $request): Response
    {
        try {
            $query = WebFingerQuery::parse($request->server->getString('QUERY_STRING'));
        } catch (InvalidValue) {
            return $this->empty(Response::HTTP_BAD_REQUEST);
        }

        $descriptor = $this->webFinger->describe($query);

        if (!$descriptor instanceof WebFingerDescriptor) {
            return $this->empty(Response::HTTP_NOT_FOUND);
        }

        return DocumentResponses::json($descriptor->toArray(), self::CONTENT_TYPE, true);
    }

    private function empty(int $status): Response
    {
        $response = DocumentResponses::body('', 'text/plain; charset=utf-8', true);
        $response->setStatusCode($status);

        return $response;
    }
}
