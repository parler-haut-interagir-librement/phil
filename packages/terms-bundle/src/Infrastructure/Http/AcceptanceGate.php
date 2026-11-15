<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Http;

use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Contract\MemberResolver;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stops a signed-in member who has a version to accept, after the firewall has read the token: a page is sent to the
 * acceptance page, with the address to come back to; an API is answered `403` with an RFC 9457 problem as
 * `application/problem+json`, which lists the versions to accept.
 *
 * A visitor, a sub-request, the bundle's own routes, the exempt routes and the paths under an exempt prefix pass. A
 * request is an API's when its path is under an API prefix, its format is JSON, or its first acceptable type is JSON.
 */
final readonly class AcceptanceGate
{
    public const string ROUTE_PREFIX = 'phil_terms_';

    public const string TITLE = 'Terms to accept';

    public const string DETAIL = 'A version of the terms awaits your acceptance.';

    /**
     * @param list<string> $exemptRoutes
     * @param list<string> $exemptPaths
     * @param list<string> $apiPaths
     */
    public function __construct(
        private AcceptanceChecker $checker,
        private MemberResolver $members,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
        private array $exemptRoutes = [],
        private array $exemptPaths = [],
        private array $apiPaths = [],
        private string $problemType = 'about:blank',
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $this->isExempt($request)) {
            return;
        }

        $member = $this->members->current();
        $pending = null === $member ? [] : $this->checker->pending($member, $this->clock->now());

        if ([] === $pending) {
            return;
        }

        $event->setResponse($this->isApi($request) ? $this->problem($pending) : new RedirectResponse(
            $this->urls->generate(AcceptanceController::ROUTE, ['target' => $request->getRequestUri()]),
            Response::HTTP_SEE_OTHER,
        ));
    }

    private function isExempt(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        if (\is_string($route) && (str_starts_with($route, self::ROUTE_PREFIX) || \in_array($route, $this->exemptRoutes, true))) {
            return true;
        }

        return $this->under($request->getPathInfo(), $this->exemptPaths);
    }

    private function isApi(Request $request): bool
    {
        if ($this->under($request->getPathInfo(), $this->apiPaths)) {
            return true;
        }

        $format = $request->getRequestFormat(null);

        if (null !== $format) {
            return \in_array($format, ['json', 'jsonld', 'jsonapi', 'problem'], true);
        }

        $first = $request->getAcceptableContentTypes()[0] ?? '';

        return 'application/json' === $first || str_ends_with($first, '+json');
    }

    /**
     * @param list<string> $prefixes
     */
    private function under(string $path, array $prefixes): bool
    {
        return array_any($prefixes, static fn (string $prefix): bool => str_starts_with($path, $prefix));
    }

    /**
     * @param non-empty-list<PolicyVersion> $pending
     */
    private function problem(array $pending): JsonResponse
    {
        return new JsonResponse([
            'type' => $this->problemType,
            'title' => $this->translator?->trans(self::TITLE, [], 'phil_terms') ?? self::TITLE,
            'status' => Response::HTTP_FORBIDDEN,
            'detail' => $this->translator?->trans(self::DETAIL, [], 'phil_terms') ?? self::DETAIL,
            'accept' => $this->urls->generate(AcceptanceController::ROUTE),
            'pending' => array_map(fn (PolicyVersion $version): array => [
                'policy' => $version->policy,
                'version' => $version->number,
                'url' => $this->urls->generate(PolicyController::VERSION_ROUTE, ['policy' => $version->policy, 'number' => $version->number]),
            ], $pending),
        ], Response::HTTP_FORBIDDEN, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
    }
}
