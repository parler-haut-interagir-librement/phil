<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * The claim of the Names and Role Provisioning Service in a launch: where the members of the context are listed.
 */
final readonly class NrpsEndpoint
{
    /**
     * @param list<string> $serviceVersions
     */
    public function __construct(
        public Url $membershipsUrl,
        public array $serviceVersions = ['2.0'],
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        return new self($claim->url('context_memberships_url'), $claim->stringList('service_versions', 8));
    }

    /**
     * @return array{context_memberships_url: string, service_versions: list<string>}
     */
    public function toClaim(): array
    {
        return ['context_memberships_url' => $this->membershipsUrl->toString(), 'service_versions' => $this->serviceVersions];
    }
}
