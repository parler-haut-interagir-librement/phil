<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Http;

use Phil\EmailAddressBundle\Application\EmailAddresses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The lists the Stimulus controller suggests and warns from, the same the server uses: the known domains, most used
 * first; the top-level slips and what they stand for; the parts before the @ that name a role. The answer is the same
 * for everyone, public and kept a day; a request that sends back its ETag gets `304 Not Modified`.
 */
final readonly class EmailDomainsController
{
    /**
     * @param list<string>          $domains the domains the suggester knows
     * @param array<string, string> $slips   the top-level slips
     */
    public function __construct(
        private EmailAddresses $addresses,
        private array $domains,
        private array $slips,
        private int $maxAge = 86400,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $response = new JsonResponse(['domains' => $this->domains, 'slips' => (object) $this->slips, 'roles' => $this->addresses->roleLocalParts()]);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
