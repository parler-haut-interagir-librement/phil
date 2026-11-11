<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Twig;

use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Twig\Attribute\AsTwigFunction;

/**
 * The flags in a template, read through the same evaluator as the controllers, so a page and its controller agree
 * within a request:
 *
 * ```twig
 * {% if feature_enabled('new-checkout') %}…{% endif %}
 * {% if feature_variant('checkout-layout') == 'two-columns' %}…{% endif %}
 * ```
 */
final readonly class FeatureFlagsExtension
{
    public function __construct(
        private FlagEvaluator $flags,
    ) {
    }

    #[AsTwigFunction(name: 'feature_enabled')]
    public function enabled(string $flag): bool
    {
        return $this->flags->isEnabled(FlagName::of($flag));
    }

    #[AsTwigFunction(name: 'feature_variant')]
    public function variant(string $flag): ?string
    {
        return $this->flags->variant(FlagName::of($flag))?->value;
    }
}
