<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * Values a form does not accept: for each field, what is wrong with it — `required`, `too_long`, `email`, `url`,
 * `date`, `birth_date`, `too_young`, `too_old`, `choice`, `disqualified`, `full`, `type` —, or `closed` when the form
 * takes no entry.
 */
final class InvalidEntry extends InvalidArgumentException implements FormsException
{
    /**
     * @param array<string, string> $errors
     */
    private function __construct(
        public readonly array $errors,
    ) {
        $listed = [];

        foreach ($errors as $field => $error) {
            $listed[] = $field.': '.$error;
        }

        parent::__construct('The entry is refused: '.implode(', ', $listed).'.');
    }

    /**
     * @param array<string, string> $errors
     */
    public static function with(array $errors): self
    {
        return new self($errors);
    }
}
