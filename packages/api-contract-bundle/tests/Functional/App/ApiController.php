<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional\App;

use Phil\ApiContract\Problem\ProblemDetails;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;
use Phil\ApiContractBundle\Infrastructure\Http\Idempotent;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The endpoints of the tests: an order created once per key, a request that fails, a missing resource, an old one,
 * and an old one that takes an idempotency key.
 */
final class ApiController
{
    public int $orders = 0;

    #[Idempotent]
    public function createOrder(): JsonResponse
    {
        ++$this->orders;

        return new JsonResponse(['order' => $this->orders], Response::HTTP_CREATED, ['Location' => '/api/orders/'.$this->orders, 'X-Trace' => 'abc']);
    }

    #[Idempotent(ttl: 60)]
    public function failing(): Response
    {
        ++$this->orders;

        throw new ProblemException(ProblemDetails::create(Response::HTTP_SERVICE_UNAVAILABLE, title: 'Try later'), ['Retry-After' => '30']);
    }

    public function missing(): Response
    {
        throw new NotFoundHttpException('No such order.');
    }

    #[DeprecatedEndpoint(deprecatedAt: '2026-11-01', sunsetAt: '2027-05-01', link: 'https://example.com/changes')]
    public function old(): JsonResponse
    {
        return new JsonResponse(['old' => true]);
    }

    #[Idempotent]
    #[DeprecatedEndpoint(deprecatedAt: '2026-11-01', sunsetAt: '2027-05-01')]
    public function oldOrder(): JsonResponse
    {
        ++$this->orders;

        return new JsonResponse(['order' => $this->orders], Response::HTTP_CREATED);
    }
}
