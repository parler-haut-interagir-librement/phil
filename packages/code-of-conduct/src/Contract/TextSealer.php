<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Contract;

use Phil\CodeOfConduct\Exception\UnreadableText;
use SensitiveParameter;

/**
 * Seals and opens the texts of the reports: what happened, and the notes of the responsible people. The context,
 * built from the report and the note, is authenticated with the text: a sealed text moved elsewhere does not open.
 *
 * The package's adapter is `KeyManagement\KeyManagementTextSealer`, the envelope encryption of Symfony's
 * KeyManagement component.
 */
interface TextSealer
{
    /**
     * @return string the sealed text, printable, safe to store in a text column
     */
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string;

    /**
     * @throws UnreadableText when the text does not open under this context
     */
    public function open(string $sealed, string $context): string;
}
