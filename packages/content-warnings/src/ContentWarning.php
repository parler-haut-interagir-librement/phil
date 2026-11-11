<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use Phil\ContentWarnings\Exception\InvalidWarning;

/**
 * One warning of a piece of content: the key of its theme, how severe the theme is there when it is known, and an
 * optional note of 500 characters at most — `gore`, severe, "the final battle in chapter 12".
 *
 * A warning names its theme by key: content keeps its warnings when a label changes, and a theme an application
 * removes from its catalogue stays on the content that named it.
 */
final readonly class ContentWarning
{
    public const int MAX_NOTE_LENGTH = 500;

    public ?string $note;

    /**
     * @throws InvalidWarning for a malformed theme key or a note over 500 characters
     */
    public function __construct(
        public string $theme,
        public ?Severity $severity = null,
        ?string $note = null,
    ) {
        if (!Key::isValid($theme)) {
            throw InvalidWarning::because(\sprintf('"%s" is not a theme key', mb_substr($theme, 0, 80)));
        }

        $note = null === $note ? null : trim($note);

        if (null !== $note && mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw InvalidWarning::because(\sprintf('the note of "%s" is over %d characters', $theme, self::MAX_NOTE_LENGTH));
        }

        $this->note = '' === $note ? null : $note;
    }
}
