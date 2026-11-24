<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\SurveyBundle\Application\Contract\QuestionnaireTemplates;
use Phil\SurveyBundle\Application\Exception\UnknownQuestionnaire;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;

/**
 * The questionnaires of the application: saved once their redirects are checked, read, removed, exported and imported
 * as JSON, and made from a template.
 */
final readonly class Questionnaires
{
    public function __construct(
        private QuestionnaireRepository $repository,
        private RedirectPolicy $redirects,
        private QuestionnaireTemplates $templates = new BuiltInTemplates(),
    ) {
    }

    /**
     * Adds the questionnaire, or replaces the one of its key.
     *
     * @throws InvalidQuestionnaire when an ending redirects off the origins the application allows
     */
    public function save(Questionnaire $questionnaire): void
    {
        foreach ($questionnaire->endings as $ending) {
            if (null !== $ending->redirect) {
                $this->redirects->check($ending->redirect);
            }
        }

        $this->repository->save($questionnaire);
    }

    public function find(string $key): ?Questionnaire
    {
        return $this->repository->find($key);
    }

    /**
     * @throws UnknownQuestionnaire when no questionnaire has the key
     */
    public function get(string $key): Questionnaire
    {
        return $this->repository->find($key) ?? throw UnknownQuestionnaire::withKey($key);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->repository->keys();
    }

    /**
     * Removes the questionnaire with its responses, counts and marks.
     */
    public function remove(string $key): bool
    {
        return $this->repository->remove($key);
    }

    /**
     * @throws UnknownQuestionnaire when no questionnaire has the key
     */
    public function export(string $key): string
    {
        return QuestionnaireJson::encode($this->get($key));
    }

    /**
     * Reads a questionnaire from its JSON and saves it.
     *
     * @param bool $replace whether it replaces the questionnaire of its key; refused otherwise
     *
     * @throws InvalidQuestionnaire when the JSON is refused, or its key is taken and not to be replaced
     */
    public function import(string $json, bool $replace = false): Questionnaire
    {
        $questionnaire = QuestionnaireJson::decode($json);

        if (!$replace && $this->repository->find($questionnaire->key) instanceof Questionnaire) {
            throw InvalidQuestionnaire::because(\sprintf('A questionnaire has the key "%s" already.', $questionnaire->key));
        }

        $this->save($questionnaire);

        return $questionnaire;
    }

    /**
     * @return array<string, string> the title of each template, by name
     */
    public function templates(): array
    {
        return $this->templates->names();
    }

    /**
     * Makes a questionnaire from a template, under its key and, when given, its title, and saves it.
     *
     * @throws UnknownQuestionnaire when no provider knows the template
     * @throws InvalidQuestionnaire when the template is refused, or the key is taken
     */
    public function fromTemplate(string $template, string $key, ?string $title = null): Questionnaire
    {
        $data = $this->templates->template($template) ?? throw UnknownQuestionnaire::template($template);
        $data['key'] = $key;

        if (null !== $title) {
            $data['title'] = $title;
        }

        if ($this->repository->find($key) instanceof Questionnaire) {
            throw InvalidQuestionnaire::because(\sprintf('A questionnaire has the key "%s" already.', $key));
        }

        $questionnaire = Questionnaire::fromArray($data);
        $this->save($questionnaire);

        return $questionnaire;
    }
}
