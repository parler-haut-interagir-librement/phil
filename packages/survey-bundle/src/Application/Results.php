<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\Poll\Ballot;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\NetPromoterScore;
use Phil\Poll\Counting\ScaleCount;
use Phil\Poll\CountingMethods;
use Phil\Poll\PollRules;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;

/**
 * The aggregated results, counted by phil/poll from the stored answers, read one response at a time: the count of
 * each option of a choice and of a yes or no, the Borda count of a ranking, the distribution and mean of a scale, the
 * Net Promoter Score, and the count of each row of a matrix. Free answers are never counted.
 *
 * Nothing is shown below the questionnaire's minimum: neither the report while it counts fewer responses, nor a
 * question fewer of them answered. An answer that no longer fits its question — an option since removed — is left out.
 */
final readonly class Results
{
    private CountingMethods $methods;

    public function __construct(
        private ResponseRepository $responses,
        ?CountingMethods $methods = null,
    ) {
        $this->methods = $methods ?? CountingMethods::defaults();
    }

    /**
     * The results for those who run the questionnaire — about the subject, when given, such as a speaker's session.
     */
    public function of(Questionnaire $questionnaire, ?string $subject = null): ResultsReport
    {
        $responses = $this->responses->count($questionnaire->key, $subject);
        $minimum = $questionnaire->minimumResponses;

        if ($responses < $minimum) {
            return new ResultsReport($responses, $minimum, true, []);
        }

        $questions = [];

        foreach ($questionnaire->questions() as $question) {
            if ($question->type->isCounted()) {
                $questions[] = $this->question($questionnaire, $question, $subject);
            }
        }

        return new ResultsReport($responses, $minimum, false, $questions);
    }

    /**
     * The results shown to a respondent after answering, when the questionnaire shows them; it does not by default.
     *
     * @throws SurveyRefused when the questionnaire keeps its results private
     */
    public function public(Questionnaire $questionnaire, ?string $subject = null): ResultsReport
    {
        if (!$questionnaire->publicResults) {
            throw SurveyRefused::resultsPrivate();
        }

        return $this->of($questionnaire, $subject);
    }

    private function question(Questionnaire $questionnaire, Question $question, ?string $subject): QuestionResult
    {
        $rules = $question->rules();
        \assert($rules instanceof PollRules);
        $method = match ($question->type) {
            QuestionType::Ranking => Borda::NAME,
            QuestionType::Scale => ScaleCount::NAME,
            QuestionType::NetPromoter => NetPromoterScore::NAME,
            default => ChoiceCount::NAME,
        };
        $minimum = $questionnaire->minimumResponses;

        if (QuestionType::Matrix !== $question->type) {
            $answered = 0;
            $result = $this->methods->count($method, $rules, $this->ballots($this->responses->answers($questionnaire->key, $question->name, $subject), $rules, $answered));

            return $answered < $minimum ? new QuestionResult($question, $answered, true) : new QuestionResult($question, $answered, false, $result);
        }

        $rows = [];
        $least = null;

        foreach ($question->rowValues() as $row) {
            $answered = 0;
            $cells = (function () use ($questionnaire, $question, $subject, $row): iterable {
                foreach ($this->responses->answers($questionnaire->key, $question->name, $subject) as $answer) {
                    yield \is_array($answer) ? $answer[$row] ?? null : null;
                }
            })();
            $rows[$row] = $this->methods->count($method, $rules, $this->ballots($cells, $rules, $answered));
            $least = null === $least ? $answered : min($least, $answered);
        }

        $least ??= 0;

        return $least < $minimum ? new QuestionResult($question, $least, true) : new QuestionResult($question, $least, false, null, $rows);
    }

    /**
     * @param iterable<mixed> $answers
     *
     * @return iterable<Ballot>
     */
    private function ballots(iterable $answers, PollRules $rules, int &$answered): iterable
    {
        foreach ($answers as $answer) {
            $choices = match (true) {
                \is_bool($answer) => [$answer ? 'yes' : 'no'],
                \is_int($answer), \is_string($answer) => [(string) $answer],
                \is_array($answer) && array_is_list($answer) => $answer,
                default => null,
            };

            $fitting = null === $choices ? null : $this->fitting($choices, $rules);

            if (null === $fitting) {
                continue;
            }

            ++$answered;

            yield Ballot::of($fitting);
        }
    }

    /**
     * The choices, when they still fit the question: options it has, each once, no more than it takes.
     *
     * @param list<mixed> $choices
     *
     * @return non-empty-list<string>|null
     */
    private function fitting(array $choices, PollRules $rules): ?array
    {
        $fitting = [];

        foreach ($choices as $choice) {
            if (!\is_string($choice) || !$rules->has($choice) || \in_array($choice, $fitting, true)) {
                return null;
            }

            $fitting[] = $choice;
        }

        return [] === $fitting || \count($fitting) > $rules->maxChoices ? null : $fitting;
    }
}
