<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

/**
 * The named rule sets of the verification, each one holding the one before it.
 *
 * - `Minimal`: the document is an OpenAPI 3 document a tool can read — version, info, paths, responses, path
 *   parameters, unique operation ids, local references that resolve.
 * - `Recommended`: the contract of an unversioned JSON API — no version in a path, a success response, JSON bodies,
 *   problem details for errors, a sunset for a deprecated operation, an id for every operation.
 * - `Strict`: what a client needs to read the contract without guessing — extensible enumerations, declared error
 *   responses, a description and a security requirement for every operation.
 */
enum RuleLevel: string
{
    case Minimal = 'minimal';
    case Recommended = 'recommended';
    case Strict = 'strict';

    /**
     * Whether verifying at this level runs the rules of `$level`: a level runs its own rules and those of the levels
     * listed before it.
     */
    public function includes(self $level): bool
    {
        $order = self::cases();

        return array_search($level, $order, true) <= array_search($this, $order, true);
    }
}
