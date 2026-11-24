<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Status;

use Phil\FormLogic\Typing\VariableType;

/**
 * The kinds of question: free answers, checked by phil/forms-bundle, and choices, marks, rankings and matrices,
 * checked and counted by phil/poll.
 */
enum QuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Number = 'number';
    case Date = 'date';
    case YesNo = 'yes_no';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Ranking = 'ranking';
    case Scale = 'scale';
    case NetPromoter = 'net_promoter';
    case Matrix = 'matrix';

    /**
     * Whether the question lists choices: its options, or the columns of a matrix.
     */
    public function hasChoices(): bool
    {
        return \in_array($this, [self::SingleChoice, self::MultipleChoice, self::Ranking, self::Matrix], true);
    }

    /**
     * Whether its answers are counted into results; a free answer never is.
     */
    public function isCounted(): bool
    {
        return $this->hasChoices() || self::Scale === $this || self::NetPromoter === $this || self::YesNo === $this;
    }

    /**
     * Whether the conditions that show a question or a section may read it, in the browser as on the server. A ranking
     * and a matrix answer under nested names, which only the server reads: endings and quotas may read them.
     */
    public function isReadByConditions(): bool
    {
        return self::Ranking !== $this && self::Matrix !== $this;
    }

    /**
     * The type phil/form-logic checks its conditions against, `null` for a matrix, whose rows are each a choice.
     */
    public function variableType(): ?VariableType
    {
        return match ($this) {
            self::Text, self::Textarea, self::Email => VariableType::Text,
            self::Number, self::Scale, self::NetPromoter => VariableType::Number,
            self::Date => VariableType::Date,
            self::YesNo => VariableType::Boolean,
            self::SingleChoice => VariableType::Choice,
            self::MultipleChoice, self::Ranking => VariableType::Choices,
            self::Matrix => null,
        };
    }
}
