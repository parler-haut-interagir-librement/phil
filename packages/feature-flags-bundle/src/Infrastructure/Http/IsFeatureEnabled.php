<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Http;

use Attribute;

/**
 * Puts a controller, or one of its actions, behind a flag: while the flag is off, the route answers 404, as if
 * the feature did not exist. With a variant, the route answers only to the contexts the experiment puts in that
 * variant, so each arm can have controllers of its own.
 *
 * ```php
 * #[IsFeatureEnabled('new-checkout')]
 * final class CheckoutController
 * {
 *     #[IsFeatureEnabled('checkout-layout', variant: 'two-columns')]
 *     public function twoColumns(): Response { … }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class IsFeatureEnabled
{
    public function __construct(
        public string $flag,
        public ?string $variant = null,
    ) {
    }
}
