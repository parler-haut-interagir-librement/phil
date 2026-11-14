<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * The bundle's templates, each call of the recipe `Button` of the kit drawn as a plain `<button>` with the same props
 * and attributes: the unit tests render the templates without Symfony UX Twig Component, which the functional tests
 * run with the recipe itself.
 */
final readonly class ButtonStandIn implements LoaderInterface
{
    private const string CALL = "/\\{% component \\(phil_onboarding_kit\\('Button'\\)\\) with (\\{.*?\\}) %\\}\\{% block content %\\}(.*?)\\{% endblock %\\}\\{% endcomponent %\\}/";

    private const string BUTTON = '{% set _button = $1 %}<button type="{{ _button.type ?? \'button\' }}" data-variant="{{ _button.variant ?? \'default\' }}" data-size="{{ _button.size ?? \'default\' }}"{% for _name, _value in _button|filter((v, k) => k not in [\'type\', \'variant\', \'size\']) %} {{ _name }}="{{ _value }}"{% endfor %}>$2</button>';

    public function __construct(
        private LoaderInterface $inner,
    ) {
    }

    #[Override]
    public function getSourceContext(string $name): Source
    {
        $source = $this->inner->getSourceContext($name);

        return new Source((string) preg_replace(self::CALL, self::BUTTON, $source->getCode()), $source->getName(), $source->getPath());
    }

    #[Override]
    public function getCacheKey(string $name): string
    {
        return 'button-stand-in:'.$this->inner->getCacheKey($name);
    }

    #[Override]
    public function isFresh(string $name, int $time): bool
    {
        return $this->inner->isFresh($name, $time);
    }

    #[Override]
    public function exists(string $name): bool
    {
        return $this->inner->exists($name);
    }
}
