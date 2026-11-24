<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Contract;

/**
 * A provider of questionnaire templates, tagged `phil_survey.template_provider`: a template is the data of a
 * questionnaire, as its JSON schema describes it, without its key, which the questionnaire made from it takes. The
 * first provider that knows a name gives its template.
 */
interface QuestionnaireTemplates
{
    /**
     * @return array<string, string> the title of each template, by name
     */
    public function names(): array;

    /**
     * @return array<string, mixed>|null the template's data, without a key, or null when the provider does not know it
     */
    public function template(string $name): ?array;
}
