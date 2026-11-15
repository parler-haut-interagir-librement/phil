<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Platform\Nrps\NrpsService;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Service\Nrps\MediaType;
use Phil\Lti\Service\Nrps\Member;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Names and Role Provisioning Service of the platform over HTTP: the members of a context, a page at a time, the
 * next page in the `Link` header; a context that is not the tool's answers 404.
 */
final readonly class NrpsController
{
    public const string ROUTE = 'phil_lti_platform_memberships';

    public function __construct(
        private ServiceGuard $guard,
        private NrpsService $nrps,
        private PlatformUrls $urls,
    ) {
    }

    public function __invoke(Request $request, string $context): Response
    {
        $role = ServiceResponses::stringQuery($request, 'role');
        $limit = ServiceResponses::intQuery($request, 'limit');

        try {
            $page = $this->nrps->page($this->guard->authorize($request->headers->get('Authorization'), [Scope::MEMBERSHIP_READ_ONLY]), $context, $role, $limit, ServiceResponses::intQuery($request, 'offset') ?? 0);
        } catch (AccessDenied $accessDenied) {
            return ServiceResponses::denied($accessDenied);
        }

        if (null === $page) {
            return ServiceResponses::notFound();
        }

        $headers = [];

        if (null !== $page['nextOffset']) {
            $query = array_filter(['role' => $role, 'limit' => $limit, 'offset' => $page['nextOffset']], static fn (string|int|null $value): bool => null !== $value);
            $headers['Link'] = '<'.$this->urls->url(self::ROUTE, ['context' => $context] + $query)->toString().'>; rel="next"';
        }

        return ServiceResponses::json([
            'id' => $this->urls->url(self::ROUTE, ['context' => $context])->toString(),
            'context' => $page['context']->toClaim(),
            'members' => array_map(static fn (Member $member): array => $member->toArray(), $page['members']),
        ], MediaType::MEMBERSHIP_CONTAINER, headers: $headers);
    }
}
