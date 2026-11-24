<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Override;
use Phil\SurveyBundle\Application\Contract\QuestionnaireTemplates;

/**
 * Every provider of templates as one: the names of all, and each template from the first provider that knows it.
 */
final readonly class TemplateChain implements QuestionnaireTemplates
{
    /**
     * @param iterable<QuestionnaireTemplates> $providers
     */
    public function __construct(private iterable $providers)
    {
    }

    #[Override]
    public function names(): array
    {
        $names = [];

        foreach ($this->providers as $provider) {
            $names += $provider->names();
        }

        ksort($names);

        return $names;
    }

    #[Override]
    public function template(string $name): ?array
    {
        foreach ($this->providers as $provider) {
            $template = $provider->template($name);

            if (null !== $template) {
                return $template;
            }
        }

        return null;
    }
}
