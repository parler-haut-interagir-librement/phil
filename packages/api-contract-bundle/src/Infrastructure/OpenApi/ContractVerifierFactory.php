<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\OpenApi;

use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\ContractVerifier;

/**
 * The verifier of the container: the rules `phil/api-contract` ships, then the application's own, tagged
 * `phil_api_contract.openapi_rule`, without those `verification.skip` names.
 */
final class ContractVerifierFactory
{
    /**
     * @param iterable<OpenApiRule> $rules
     * @param list<string>          $skip
     */
    public static function create(iterable $rules, array $skip): ContractVerifier
    {
        return ContractVerifier::withBuiltInRules(...array_values([...$rules]))->without(...$skip);
    }
}
