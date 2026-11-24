<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Model\Read;

/**
 * An option of a question, or a row or column of a matrix: the value an answer holds, 1 to 64 ASCII letters, digits,
 * "_" or "-" as phil/poll identifies its options, and the label respondents read.
 */
final readonly class Choice
{
    public const int MAX_LABEL = 255;

    public string $label;

    /**
     * @throws InvalidQuestionnaire when the value or the label is out of bounds
     */
    public function __construct(public string $value, string $label)
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value)) {
            throw InvalidQuestionnaire::because(\sprintf('A choice is 1 to 64 ASCII letters, digits, "_" or "-"; "%s" is not.', mb_substr($value, 0, 80)));
        }

        $this->label = Read::text($label, 'the label of the choice "'.$value.'"', self::MAX_LABEL);
    }

    /**
     * @throws InvalidQuestionnaire when the choice is not `{"value": …, "label": …}`
     */
    public static function fromArray(mixed $data): self
    {
        $choice = Read::map($data, 'a choice', ['value', 'label']);

        return new self(Read::string($choice, 'value', 'a choice'), Read::string($choice, 'label', 'a choice'));
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label];
    }
}
