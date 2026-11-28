<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;

/**
 * The email has a plain-text part, and the part gives every address the HTML links to — `http:`, `https:` and
 * `mailto:`, the last without its scheme — so that a reader of the text reaches what a reader of the HTML reaches.
 */
final readonly class TextPart implements Rule
{
    public const string NAME = 'text-part';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedEmail $email): iterable
    {
        if ('' === trim($email->text)) {
            yield Finding::error(self::NAME, 'The email has no plain-text part.');

            return;
        }

        $seen = [];

        foreach ($email->document->getElementsByTagName('a') as $link) {
            $href = trim($link->getAttribute('href') ?? '');
            $target = $this->target($href);

            if (null === $target || isset($seen[$href])) {
                continue;
            }

            $seen[$href] = true;

            if (!str_contains($email->text, $target)) {
                yield Finding::error(self::NAME, 'The plain-text part does not give the address of a link.', $href);
            }
        }
    }

    /**
     * What the plain-text part writes for a link: the address of a web link, the address alone of a `mailto:` link —
     * which, empty, every text holds —, nothing for another scheme.
     */
    private function target(string $href): ?string
    {
        $lowercase = strtolower($href);

        if (str_starts_with($lowercase, 'mailto:')) {
            return substr($href, 7);
        }

        return str_starts_with($lowercase, 'http://') || str_starts_with($lowercase, 'https://') ? $href : null;
    }
}
