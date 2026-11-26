<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Infrastructure;

use Override;
use Phil\CorsBundle\Application\PolicyResolver;
use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Domain\PathRule;
use Symfony\Component\HttpFoundation\Request;

/**
 * The policy of the first rule whose pattern matches the path of the request, in the order of the configuration; none
 * when no rule matches.
 */
final readonly class PathPolicyResolver implements PolicyResolver
{
    /**
     * @param list<PathRule> $rules
     */
    public function __construct(private array $rules)
    {
    }

    #[Override]
    public function resolve(Request $request): ?CorsPolicy
    {
        $path = $request->getPathInfo();

        foreach ($this->rules as $rule) {
            if ($rule->matches($path)) {
                return $rule->policy;
            }
        }

        return null;
    }
}
