<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Field;

/**
 * What a field takes.
 */
enum FieldType: string
{
    /** One line of text. */
    case Text = 'text';

    /** Several lines of text. */
    case Textarea = 'textarea';

    /** An email address. */
    case Email = 'email';

    /** An `http` or `https` URL. */
    case Url = 'url';

    /** A date, `YYYY-MM-DD`. */
    case Date = 'date';

    /**
     * A birth date, `YYYY-MM-DD`: not after the day the entry comes, nor more than 130 years before it.
     */
    case BirthDate = 'birth_date';

    /** One of the field's choices. */
    case Choice = 'choice';

    /** Ticked or not. */
    case Checkbox = 'checkbox';

    /**
     * The longest value a field of this type takes when its definition says nothing.
     */
    public function defaultMaxLength(): ?int
    {
        return match ($this) {
            self::Textarea => 5000,
            self::Text, self::Email, self::Url => 255,
            default => null,
        };
    }
}
