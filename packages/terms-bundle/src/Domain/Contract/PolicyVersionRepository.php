<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain\Contract;

use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * Where the versions of the policies are kept. A version is added once and never changed nor removed.
 */
interface PolicyVersionRepository
{
    /**
     * @throws InvalidTerms when the policy already has a version of that number
     */
    public function add(PolicyVersion $version): void;

    /**
     * The versions of a policy, the newest first.
     *
     * @return list<PolicyVersion>
     */
    public function all(string $policy): array;
}
