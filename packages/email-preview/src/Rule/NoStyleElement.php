<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;

/**
 * The styles of the email are inlined: no `<style>` element remains, which several mail clients drop or strip.
 */
final readonly class NoStyleElement implements Rule
{
    public const string NAME = 'no-style-element';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedEmail $email): iterable
    {
        $count = $email->document->getElementsByTagName('style')->length;

        if ($count > 0) {
            yield Finding::error(self::NAME, 'The email keeps a <style> element: inline its styles.', (string) $count);
        }
    }
}
