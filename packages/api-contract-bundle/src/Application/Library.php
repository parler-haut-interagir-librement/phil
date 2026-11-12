<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Application;

use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\Deprecation\ObservedDeprecations;
use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\Idempotency\Contract\IdempotencyStore;

/**
 * The ports of `phil/api-contract` and `phil/idempotency` the bundle aliases, named here so that the bundle class wires them through its
 * own application layer.
 */
final class Library
{
    public const string CONTRACT_VERIFIER = ContractVerifier::class;

    public const string IDEMPOTENCY_STORE = IdempotencyStore::class;

    public const string OBSERVED_DEPRECATIONS = ObservedDeprecations::class;

    public const string OPENAPI_RULE = OpenApiRule::class;
}
