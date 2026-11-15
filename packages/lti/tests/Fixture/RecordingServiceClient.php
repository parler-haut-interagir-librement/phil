<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Fixture;

use Closure;
use Override;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;

/**
 * Answers each request with the next answer given, or by a handler, and keeps the requests.
 */
final class RecordingServiceClient implements ServiceClient
{
    /** @var list<ServiceRequest> */
    public array $requests = [];

    /** @var list<ServiceResponse> */
    private array $responses;

    /**
     * @param (Closure(ServiceRequest): ServiceResponse)|null $handler
     */
    public function __construct(
        private readonly ?Closure $handler = null,
        ServiceResponse ...$responses,
    ) {
        $this->responses = array_values($responses);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): ServiceResponse
    {
        return new ServiceResponse($status, $headers, \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
    }

    #[Override]
    public function send(ServiceRequest $request): ServiceResponse
    {
        $this->requests[] = $request;

        if ($this->handler instanceof Closure) {
            return ($this->handler)($request);
        }

        return array_shift($this->responses) ?? new ServiceResponse(500, [], '');
    }
}
