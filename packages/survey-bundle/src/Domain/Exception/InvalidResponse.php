<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Exception;

use DomainException;

/**
 * An answer the questionnaire refuses: the error of each question, by its name — `required`, `type`, `too_long`,
 * `email`, `date`, `choice`, `too_few`, `too_many`, `range`.
 */
final class InvalidResponse extends DomainException implements SurveyException
{
    /**
     * @param array<string, string> $errors the error of each question, by its name
     */
    public function __construct(string $message, public readonly array $errors)
    {
        parent::__construct($message);
    }

    /**
     * @param non-empty-array<string, string> $errors the error of each question, by its name
     */
    public static function withErrors(array $errors): self
    {
        $named = [];

        foreach ($errors as $question => $error) {
            $named[] = $question.': '.$error;
        }

        return new self('The answer is refused: '.implode(', ', $named).'.', $errors);
    }
}
