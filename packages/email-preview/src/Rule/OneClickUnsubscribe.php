<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;

/**
 * An email sent in bulk can be left in one click, as RFC 8058 describes it and as the large mailbox providers require:
 * a `List-Unsubscribe` header with an `https:` address, and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`. An
 * email that is not sent in bulk owes neither.
 */
final readonly class OneClickUnsubscribe implements Rule
{
    public const string NAME = 'one-click-unsubscribe';

    public const string POST_VALUE = 'List-Unsubscribe=One-Click';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedEmail $email): iterable
    {
        if (!$email->sending->bulk) {
            return;
        }

        $unsubscribe = trim($email->sending->header('List-Unsubscribe') ?? '');

        if ('' === $unsubscribe) {
            yield Finding::error(self::NAME, 'An email sent in bulk has no List-Unsubscribe header.');
        } elseif (!str_contains(strtolower($unsubscribe), '<https:')) {
            yield Finding::error(self::NAME, 'The List-Unsubscribe header gives no https address to unsubscribe in one click.', $unsubscribe);
        }

        $post = $email->sending->header('List-Unsubscribe-Post');

        if (null === $post) {
            yield Finding::error(self::NAME, 'An email sent in bulk has no List-Unsubscribe-Post header.');
        } elseif (self::POST_VALUE !== trim($post)) {
            yield Finding::error(self::NAME, 'The List-Unsubscribe-Post header is not "'.self::POST_VALUE.'".', $post);
        }
    }
}
