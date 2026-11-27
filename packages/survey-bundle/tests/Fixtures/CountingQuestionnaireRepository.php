<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Fixtures;

use Override;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;

/**
 * A repository that counts the saves it is asked for and keeps the last questionnaire, before passing it on.
 */
final class CountingQuestionnaireRepository implements QuestionnaireRepository
{
    public int $saves = 0;

    public ?Questionnaire $last = null;

    public function __construct(public readonly QuestionnaireRepository $inner)
    {
    }

    #[Override]
    public function save(Questionnaire $questionnaire): void
    {
        ++$this->saves;
        $this->last = $questionnaire;
        $this->inner->save($questionnaire);
    }

    #[Override]
    public function find(string $key): ?Questionnaire
    {
        return $this->inner->find($key);
    }

    #[Override]
    public function keys(): array
    {
        return $this->inner->keys();
    }

    #[Override]
    public function retentions(): array
    {
        return $this->inner->retentions();
    }

    #[Override]
    public function remove(string $key): bool
    {
        return $this->inner->remove($key);
    }
}
