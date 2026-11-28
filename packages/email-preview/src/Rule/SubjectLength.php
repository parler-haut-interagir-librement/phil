<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;

/**
 * The subject reads whole in a list of messages: a warning above 50 characters by default, counted as a reader sees
 * them — a letter and its accent, an emoji and its modifiers count once.
 */
final readonly class SubjectLength implements Rule
{
    public const string NAME = 'subject-length';

    public const int DEFAULT_LENGTH = 50;

    public function __construct(
        public int $length = self::DEFAULT_LENGTH,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedEmail $email): iterable
    {
        $subject = trim($email->subject);
        $length = grapheme_strlen($subject);

        if (!\is_int($length)) {
            $length = mb_strlen($subject, 'UTF-8');
        }

        if ($length > $this->length) {
            yield Finding::warning(self::NAME, \sprintf('The subject holds %d characters; a list of messages shows about %d.', $length, $this->length), $subject);
        }
    }
}
