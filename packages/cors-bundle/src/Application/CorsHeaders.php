<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Application;

use Phil\CorsBundle\Domain\CorsPolicy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CORS headers of a policy: the answer to a preflight, and the headers of the response to a cross-origin request.
 *
 * Every answer varies on Origin, so that a shared cache never gives the headers of one origin to another. A preflight
 * whose origin, method or headers the policy refuses gets no Access-Control-Allow-* header at all, which the browser
 * reads as a refusal. The service holds no state between requests.
 */
final readonly class CorsHeaders
{
    /**
     * The longest Access-Control-Request-Headers value read, and the most names in it; beyond either the preflight is
     * refused.
     */
    public const int REQUEST_HEADERS_MAX_BYTES = 4096;

    public const int REQUEST_HEADERS_MAX_COUNT = 64;

    private const array ALLOW_HEADERS = [
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials',
        'Access-Control-Expose-Headers',
    ];

    /**
     * Whether the request is a preflight: OPTIONS, with an Origin and the method it asks for.
     */
    public function isPreflight(Request $request): bool
    {
        return Request::METHOD_OPTIONS === $request->getMethod()
            && $request->headers->has('Origin')
            && $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * The answer to a preflight: 204, with the Access-Control-Allow-* headers only when the policy allows the origin,
     * the method and every header asked for.
     */
    public function preflight(Request $request, CorsPolicy $policy): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $vary = ['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers'];

        if ($policy->allowPrivateNetwork) {
            $vary[] = 'Access-Control-Request-Private-Network';
        }

        $response->setVary($vary);
        $origin = (string) $request->headers->get('Origin');
        $headers = $this->requestedHeaders((string) $request->headers->get('Access-Control-Request-Headers'));

        if (null === $headers
            || !$policy->allowsOrigin($origin)
            || !$policy->allowsMethod((string) $request->headers->get('Access-Control-Request-Method'))
            || !$policy->allowsHeaders($headers)) {
            return $response;
        }

        $this->allowOrigin($response, $origin, $policy);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $policy->methods));
        $allowed = $policy->anyHeader ? $headers : $policy->allowHeaders;

        if ([] !== $allowed) {
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowed));
        }

        if (null !== $policy->maxAge) {
            $response->headers->set('Access-Control-Max-Age', (string) $policy->maxAge);
        }

        if ($policy->allowPrivateNetwork && 'true' === $request->headers->get('Access-Control-Request-Private-Network')) {
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        }

        return $response;
    }

    /**
     * The headers of the response to a request of the policy's paths: Vary: Origin always; the origin, the credentials
     * and the exposed headers when the request comes from an allowed origin; and none of them, even set by the
     * application, otherwise.
     */
    public function apply(Request $request, Response $response, CorsPolicy $policy): void
    {
        $response->setVary('Origin', false);

        foreach (self::ALLOW_HEADERS as $header) {
            $response->headers->remove($header);
        }

        $origin = $request->headers->get('Origin');

        if (null === $origin || !$policy->allowsOrigin($origin)) {
            return;
        }

        $this->allowOrigin($response, $origin, $policy);

        if ([] !== $policy->exposeHeaders) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $policy->exposeHeaders));
        }
    }

    private function allowOrigin(Response $response, string $origin, CorsPolicy $policy): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $policy->allowOriginValue($origin));

        if ($policy->allowCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * The names of Access-Control-Request-Headers, lowercase; null when the value passes the bounds.
     *
     * @return list<string>|null
     */
    private function requestedHeaders(string $value): ?array
    {
        if (\strlen($value) > self::REQUEST_HEADERS_MAX_BYTES) {
            return null;
        }

        $names = [];

        foreach (explode(',', $value) as $name) {
            $name = strtolower(trim($name, " \t"));

            if ('' !== $name) {
                $names[$name] = $name;
            }
        }

        return \count($names) > self::REQUEST_HEADERS_MAX_COUNT ? null : array_values($names);
    }
}
